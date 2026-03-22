import re
import uuid
import time
import copy
import json
import asyncio
import sys
import os
from typing import Annotated
from fastapi import APIRouter, Depends, File, Form, Request, UploadFile

# Adiciona a raiz do projeto ao path para os imports não falharem
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

from config import settings, UCEnum, PreferenciaEnum, limiter, logger
from database import registar_interacao
from core.ml_models import embeddings_model, executor
from core.cache import semantic_cache
from core.background import disparar_background
from core.utils import limpar_nome_uc, sanitizar_input, cosine_similarity
from core.retrieval import get_vector_store, get_bm25_retriever, executar_retrieval, executar_reranking
from services.ocr import processar_ocr
from services.query_expansion import e_pergunta_de_resumo, expandir_queries
from services.iaedu import chamar_iaedu
from services.calendar import processar_calendario
from prompts.rag import prompt_rag

router = APIRouter()
router = APIRouter()

@router.post("/perguntar", tags=["Alunos"])
@limiter.limit("20/minute")
async def perguntar(
    request: Request, texto: Annotated[str, Form(...)], uc: Annotated[UCEnum, Form(...)],
    thread_id: Annotated[str | None, Form()] = None, preferencia: Annotated[PreferenciaEnum, Form()] = PreferenciaEnum.textual,
    historico: Annotated[str, Form()] = "[]", imagem: Annotated[UploadFile | None, File()] = None,
):
    if not thread_id or thread_id == "string": thread_id = str(uuid.uuid4())
    else:
        try: uuid.UUID(str(thread_id))
        except ValueError: thread_id = str(uuid.uuid4())

    loop = asyncio.get_running_loop()
    uc_limpa, uc_nome = limpar_nome_uc(uc.value), uc.value

    vs = await get_vector_store(uc_limpa)
    if vs is None: return {"status": "erro", "mensagem": f"Ainda não existem documentos para a UC: {uc_nome}."}

    texto_final_aluno = sanitizar_input(texto)
    tem_imagem = False
    if imagem: texto_final_aluno, tem_imagem = await processar_ocr(imagem, settings.max_image_mb * 1024 * 1024, texto_final_aluno)

    query_emb = await loop.run_in_executor(executor, embeddings_model.embed_query, texto_final_aluno)

    for entrada in semantic_cache[uc_limpa]:
        if cosine_similarity(query_emb, entrada["emb"]) > settings.semantic_cache_threshold:
            resp_cache = copy.deepcopy(entrada["resposta"])
            resp_cache["thread_id"] = thread_id
            return resp_cache

    try: mensagens_historico = json.loads(historico)
    except Exception: mensagens_historico = []

    modo_resumo = e_pergunta_de_resumo(texto_final_aluno)
    queries_pesquisa = await expandir_queries(texto_final_aluno, tem_imagem, modo_resumo, uc_nome, mensagens_historico, thread_id, request)

    bm25 = await get_bm25_retriever(uc_limpa)
    docs_hibridos, t_retrieval = await executar_retrieval(queries_pesquisa, vs, bm25, settings.faiss_k * 2 if modo_resumo else settings.faiss_k)
    
    textos_finais, score_max, t_rerank = await executar_reranking(docs_hibridos, texto_final_aluno, settings.final_k * 3 if modo_resumo else settings.final_k * 2)

    if not textos_finais and not modo_resumo:
        return {"status": "sucesso", "thread_id": thread_id, "resposta_stu": f"Desculpa, não encontrei isso nos documentos validados de {uc_nome}."}

    contexto_recuperado = "\n\n---\n\n".join(textos_finais)
    t_llm_start = time.perf_counter()
    prompt = prompt_rag(uc_nome, contexto_recuperado, texto_final_aluno, preferencia, tem_imagem, modo_resumo)
    try: resposta_limpa = await chamar_iaedu(prompt, thread_id, request)
    except Exception: resposta_limpa = "Erro de comunicação."
    t_llm = time.perf_counter() - t_llm_start

    if preferencia == PreferenciaEnum.plano and "[CALENDARIO]" in resposta_limpa:
        resposta_limpa = await processar_calendario(resposta_limpa, uc_nome, executor, loop)

    resposta_final = {
        "status": "sucesso", "thread_id": thread_id, "pergunta_original": texto_final_aluno,
        "query_expandida": queries_pesquisa, "resposta_stu": resposta_limpa,
        "fontes_consultadas": list(set(re.findall(r"\[(.*?:\d+)\]", resposta_limpa))),
    }

    semantic_cache[uc_limpa].append({"emb": query_emb, "resposta": copy.deepcopy(resposta_final)})
    disparar_background(
        loop.run_in_executor(executor, registar_interacao, settings.sqlite_db, uc_limpa, thread_id, texto_final_aluno, str(queries_pesquisa), contexto_recuperado[:2000], resposta_limpa, score_max, t_retrieval, t_rerank, t_llm, False),
        "registar_interacao"
    )
    return resposta_final