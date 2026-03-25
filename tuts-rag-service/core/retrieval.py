import os
import time
import asyncio
from langchain_community.vectorstores import FAISS
from langchain_community.retrievers import BM25Retriever
from config import settings, FAISS_INDEX_FILE, logger
from core.cache import faiss_cache, bm25_cache, docs_cache, _cache_locks
from core.ml_models import embeddings_model, executor, reranker

async def get_vector_store(uc: str) -> FAISS | None:
    async with _cache_locks[uc]:
        if uc not in faiss_cache:
            db_path = os.path.join(settings.base_faiss_dir, uc)
            index_path = os.path.join(db_path, FAISS_INDEX_FILE)
            if os.path.exists(index_path):
                loop = asyncio.get_running_loop()
                faiss_cache[uc] = await loop.run_in_executor(
                    executor, lambda: FAISS.load_local(db_path, embeddings_model, allow_dangerous_deserialization=True)
                )
                if uc not in docs_cache:
                    docs_cache[uc] = list(faiss_cache[uc].docstore._dict.values())
            else:
                return None
    return faiss_cache[uc]

async def get_bm25_retriever(uc: str) -> BM25Retriever:
    async with _cache_locks[uc]:
        if uc not in bm25_cache:
            retriever = BM25Retriever.from_documents(docs_cache[uc])
            retriever.k = settings.bm25_k
            bm25_cache[uc] = retriever
    return bm25_cache[uc]

def _rrf_fusion(listas: list[list], k: int = 60) -> list:
    from collections import defaultdict
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

    logger.info(f"[RERANK] docs recebidos: {len(docs)}")

    paragrafos = []
    for doc in docs[:15]:
        linhas = doc.page_content.split("\n")
        cabecalho = linhas[0] if linhas[0].startswith("[CABEÇALHO FONTE:") else ""
        texto_corpo = "\n".join(linhas[1:]) if cabecalho else doc.page_content
        for p in [p.strip() for p in texto_corpo.split("\n\n") if len(p.strip()) > 20]:
            paragrafos.append(f"{cabecalho}\n{p}" if cabecalho else p)

    if not paragrafos:
        paragrafos = [doc.page_content for doc in docs[:10]]

    logger.info(f"[RERANK] parágrafos para reranking: {len(paragrafos)}")

    pares = [[texto_final, p] for p in paragrafos]
    notas = await loop.run_in_executor(executor, reranker.predict, pares)
    pars_ordenados = sorted(zip(paragrafos, notas), key=lambda x: x[1], reverse=True)

    logger.info(f"[RERANK] top scores: {[round(s,2) for _,s in pars_ordenados[:5]]}")

    textos_finais = [p for p, score in pars_ordenados[:final_k] if score > settings.score_minimo]
    score_max = float(pars_ordenados[0][1]) if pars_ordenados else 0.0
    return textos_finais, score_max, time.perf_counter() - t0