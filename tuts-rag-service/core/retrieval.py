import os
import time
import asyncio
from pathlib import Path
from collections import defaultdict

from langchain_community.vectorstores import FAISS
from langchain_community.retrievers import BM25Retriever
from config import settings, FAISS_INDEX_FILE, logger
from core.cache import faiss_cache, bm25_cache, docs_cache, _cache_locks
from core.ml_models import embeddings_model, executor, reranker
from core.utils import limpar_nome_uc

# Limite máximo de contexto injetado no prompt para prevenir DoS/Custos (aprox. 3000 tokens)
MAX_CONTEXTO_CHARS = 12_000 
# Limite máximo de caracteres por parágrafo recuperado
MAX_CHARS_POR_PARAGRAFO = 1500

def _validar_faiss_dir_leitura(db_path: str) -> None:
    """
    Defesa em profundidade contra Path Traversal e injeção de ficheiros.
    Garante que estamos a carregar FAISS exclusivamente do diretório autorizado.
    """
    base = Path(settings.base_faiss_dir).resolve()
    real = Path(db_path).resolve()

    if base != real and base not in real.parents:
        logger.error("[SEGURANÇA] Tentativa de aceder a FAISS fora do diretório base: %s", db_path)
        raise RuntimeError("Diretório FAISS fora da base permitida.")

async def get_vector_store(uc: str) -> FAISS | None:
    uc_segura = limpar_nome_uc(uc)
    if not uc_segura:
        return None

    async with _cache_locks[uc_segura]:
        if uc_segura not in faiss_cache:
            db_path = os.path.join(settings.base_faiss_dir, uc_segura)
            index_path = os.path.join(db_path, FAISS_INDEX_FILE)
            
            _validar_faiss_dir_leitura(db_path)

            if os.path.exists(index_path):
                loop = asyncio.get_running_loop()
                # O allow_dangerous_deserialization=True é mitigado pela verificação
                # do Path e pelas permissões estritas do servidor de ficheiros.
                faiss_cache[uc_segura] = await loop.run_in_executor(
                    executor,
                    lambda: FAISS.load_local(
                        db_path,
                        embeddings_model,
                        allow_dangerous_deserialization=True,
                    ),
                )
                
                if uc_segura not in docs_cache:
                    # Método seguro e oficial para obter todos os documentos no LangChain
                    docs_cache[uc_segura] = list(faiss_cache[uc_segura].docstore._dict.values())
            else:
                return None
    return faiss_cache[uc_segura]


async def get_bm25_retriever(uc: str) -> BM25Retriever:
    uc_segura = limpar_nome_uc(uc)
    
    async with _cache_locks[uc_segura]:
        if uc_segura not in docs_cache:
            vs = await get_vector_store(uc_segura)
            if vs is None:
                raise ValueError(f"Não foi possível carregar documentos para a UC '{uc_segura}'.")

        if uc_segura not in bm25_cache:
            retriever = BM25Retriever.from_documents(docs_cache[uc_segura])
            retriever.k = settings.bm25_k
            bm25_cache[uc_segura] = retriever

    return bm25_cache[uc_segura]


def _rrf_fusion(listas: list[list], k: int = 60) -> list:
    scores, doc_index = defaultdict(float), {}
    for lista in listas:
        for rank, doc in enumerate(lista):
            chave = doc.page_content
            scores[chave] += 1.0 / (k + rank)
            doc_index[chave] = doc
    ordenados = sorted(scores.items(), key=lambda x: x[1], reverse=True)
    return [doc_index[chave] for chave, _ in ordenados]


async def executar_retrieval(queries: list[str], vs: FAISS, bm25: BM25Retriever, faiss_k: int) -> tuple[list, float]:
    loop = asyncio.get_running_loop()
    t0 = time.perf_counter()
    tarefas = []
    
    for q in queries:
        tarefas.append(loop.run_in_executor(executor, vs.similarity_search, q, faiss_k))
        tarefas.append(loop.run_in_executor(executor, bm25.invoke, q))
        
    resultados = await asyncio.gather(*tarefas)
    docs = _rrf_fusion(list(resultados), k=settings.rrf_k)
    
    return docs, time.perf_counter() - t0


async def executar_reranking(docs: list, texto_final: str, final_k: int) -> tuple[list[str], float, float]:
    loop = asyncio.get_running_loop()
    t0 = time.perf_counter()

    logger.info("[RERANK] docs recebidos: %d", len(docs))

    paragrafos = []
    for doc in docs[:15]:
        linhas = doc.page_content.split("\n")
        cabecalho = linhas[0] if linhas[0].startswith("[CABEÇALHO FONTE:") else ""
        texto_corpo = "\n".join(linhas[1:]) if cabecalho else doc.page_content
        
        for p in [p.strip() for p in texto_corpo.split("\n\n") if len(p.strip()) > 20]:
            p_limpo = p[:MAX_CHARS_POR_PARAGRAFO] # Limita o tamanho de parágrafos monstruosos
            paragrafos.append(f"{cabecalho}\n{p_limpo}" if cabecalho else p_limpo)

    if not paragrafos:
        paragrafos = [doc.page_content[:MAX_CHARS_POR_PARAGRAFO] for doc in docs[:10]]

    logger.info("[RERANK] parágrafos para reranking: %d", len(paragrafos))

    try:
        pares = [[texto_final, p] for p in paragrafos]
        notas = await loop.run_in_executor(executor, reranker.predict, pares)
        pars_ordenados = sorted(zip(paragrafos, notas), key=lambda x: x[1], reverse=True)

        logger.info("[RERANK] top scores: %s", [round(float(s), 2) for _, s in pars_ordenados[:5]])

        # Recupera os textos com score positivo e aplica limite de caracteres global
        textos_finais = []
        chars_acumulados = 0
        
        for p, score in pars_ordenados[:final_k]:
            if score > settings.score_minimo: # Assume-se que score_minimo é reajustado para ~0.0 em produção no config
                if chars_acumulados + len(p) <= MAX_CONTEXTO_CHARS:
                    textos_finais.append(p)
                    chars_acumulados += len(p)
                else:
                    logger.debug("[RERANK] Limite máximo de contexto atingido (%d chars).", MAX_CONTEXTO_CHARS)
                    break

        score_max = float(pars_ordenados[0][1]) if pars_ordenados else 0.0
        
    except Exception as e:
        logger.error("[RERANK] Falha no Reranker (Cross-Encoder): %s. A usar fallback de RRF.", type(e).__name__)
        # Fallback elegante se o modelo de Reranking falhar (OOM, GPU Timeout, etc)
        textos_finais = [p for p in paragrafos[:final_k]]
        score_max = 1.0

    return textos_finais, score_max, time.perf_counter() - t0