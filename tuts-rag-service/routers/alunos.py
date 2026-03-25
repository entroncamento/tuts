import re
import uuid
import copy
import json
import asyncio
import sys
import os
from typing import Annotated
from fastapi import APIRouter, File, Form, Request, UploadFile, Header, HTTPException
from fastapi.responses import StreamingResponse

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

from config import settings, UCEnum, PreferenciaEnum, limiter, logger
from core.ml_models import embeddings_model, executor
from core.cache import semantic_cache
from core.background import disparar_background
from core.utils import limpar_nome_uc, sanitizar_input, cosine_similarity
from core.retrieval import get_vector_store, get_bm25_retriever, executar_retrieval, executar_reranking
from services.ocr import processar_ocr
from services.query_expansion import e_pergunta_de_resumo, expandir_queries
from services.calendar import processar_calendario
from prompts.rag import prompt_rag

router = APIRouter()

def sse(data: dict) -> str:
    """Helper para formatar uma linha SSE."""
    return f"data: {json.dumps(data, ensure_ascii=False)}\n\n"


@router.post("/perguntar", tags=["Alunos"])
@limiter.limit("40/minute")
async def perguntar(
    request: Request,
    texto: Annotated[str, Form(...)],
    uc: Annotated[UCEnum, Form(...)],
    x_internal_token: Annotated[str, Header()],
    thread_id: Annotated[str | None, Form()] = None,
    preferencia: Annotated[PreferenciaEnum, Form()] = PreferenciaEnum.default,
    historico: Annotated[str, Form()] = "[]",
    imagem: Annotated[UploadFile | None, File()] = None,
):
    if x_internal_token != settings.internal_token:
        raise HTTPException(status_code=403, detail="Acesso negado. Token interno inválido.")

    if not thread_id or thread_id == "string":
        thread_id = str(uuid.uuid4())
    else:
        try:
            uuid.UUID(str(thread_id))
        except ValueError:
            thread_id = str(uuid.uuid4())

    loop = asyncio.get_running_loop()
    uc_limpa = limpar_nome_uc(uc.value)
    uc_nome  = uc.value

    vs = await get_vector_store(uc_limpa)
    if vs is None:
        raise HTTPException(status_code=400, detail=f"Ainda não existem documentos para a UC: {uc_nome}.")

    texto_final_aluno = sanitizar_input(texto)
    tem_imagem = False

    if imagem:
        texto_final_aluno, tem_imagem = await processar_ocr(
            imagem, settings.max_image_mb * 1024 * 1024, texto_final_aluno
        )

    query_emb = await loop.run_in_executor(executor, embeddings_model.embed_query, texto_final_aluno)

    # ── Cache semântico — resposta instantânea ────────────────────────────────
    for entrada in semantic_cache[uc_limpa]:
        if cosine_similarity(query_emb, entrada["emb"]) > settings.semantic_cache_threshold:
            async def cached_generator():
                resp = copy.deepcopy(entrada["resposta"])
                yield sse({"status": "sucesso", "thread_id": thread_id,
                           "sem_contexto": resp.get("sem_contexto", False)})
                yield sse({"status_msg": "⚡ Resposta encontrada em cache!"})
                yield sse({"chunk": resp["resposta_stu"]})
                yield "data: [DONE]\n\n"
            return StreamingResponse(cached_generator(), media_type="text/event-stream")

    try:
        mensagens_historico = json.loads(historico)
    except Exception:
        mensagens_historico = []

    async def event_generator():
        yield sse({"status": "sucesso", "thread_id": thread_id, "sem_contexto": False})

        yield sse({"status_msg": "🧩 A interpretar a tua pergunta..."})
        modo_resumo      = e_pergunta_de_resumo(texto_final_aluno)
        queries_pesquisa = await expandir_queries(
            texto_final_aluno, tem_imagem, modo_resumo,
            uc_nome, mensagens_historico, thread_id, request
        )

        yield sse({"status_msg": "🔍 A pesquisar nos documentos da UC..."})
        bm25 = await get_bm25_retriever(uc_limpa)
        docs_hibridos, _ = await executar_retrieval(
            queries_pesquisa, vs, bm25,
            settings.faiss_k * 2 if modo_resumo else settings.faiss_k
        )

        yield sse({"status_msg": "📄 A ler e analisar os PDFs..."})
        textos_finais, _, _ = await executar_reranking(
            docs_hibridos, texto_final_aluno,
            settings.final_k * 3 if modo_resumo else settings.final_k * 2
        )

        flag_sem_contexto = not textos_finais and not modo_resumo
        yield sse({"sem_contexto": flag_sem_contexto})

        if flag_sem_contexto:
            contexto_recuperado = (
                "Responde com conhecimento geral e diz que a informação "
                "não constava nos documentos."
            )
        else:
            contexto_recuperado = "\n\n---\n\n".join(textos_finais)

        yield sse({"status_msg": "🧠 A formular a resposta..."})
        prompt = prompt_rag(
            uc_nome, contexto_recuperado, texto_final_aluno,
            preferencia, tem_imagem, modo_resumo
        )

        full_response = ""
        from services.iaedu import chamar_iaedu_stream

        try:
            async for chunk in chamar_iaedu_stream(prompt, thread_id, request):
                full_response += chunk
                yield sse({"chunk": chunk})
        except Exception as e:
            logger.error(f"Erro na IAedu Stream: {e}")
            erro_msg = "\n\n❌ Erro de comunicação com o servidor de Inteligência Artificial."
            full_response += erro_msg
            yield sse({"chunk": erro_msg})

        yield "data: [DONE]\n\n"

        if preferencia == PreferenciaEnum.plano and "[CALENDARIO]" in full_response:
            disparar_background(
                processar_calendario(full_response, uc_nome, executor, loop),
                "calendario_background"
            )

        resposta_final = {
            "status":            "sucesso",
            "thread_id":         thread_id,
            "pergunta_original": texto_final_aluno,
            "query_expandida":   queries_pesquisa,
            "resposta_stu":      full_response,
            "sem_contexto":      flag_sem_contexto,
            "fontes_consultadas": list(set(re.findall(r"\[(.*?:\d+)\]", full_response))),
        }
        if full_response and len(full_response.strip()) > 20:
            semantic_cache[uc_limpa].append({"emb": query_emb, "resposta": copy.deepcopy(resposta_final)})

    return StreamingResponse(event_generator(), media_type="text/event-stream")