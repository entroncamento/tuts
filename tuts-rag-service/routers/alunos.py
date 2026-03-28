import re
import uuid
import json
import asyncio
import random
from typing import Annotated
from fastapi import APIRouter, File, Form, Request, UploadFile, Header, HTTPException
from fastapi.responses import StreamingResponse

from config import settings, UCEnum, PreferenciaEnum, limiter, logger
from core.ml_models import embeddings_model, executor
from core.cache import procurar_cache_redis, guardar_cache_redis
from core.background import disparar_background
from core.utils import limpar_nome_uc, sanitizar_input
from core.retrieval import get_vector_store, get_bm25_retriever, executar_retrieval, executar_reranking
from services.ocr import processar_ocr
from services.query_expansion import e_pergunta_de_resumo, expandir_queries
from services.calendar import processar_calendario
from prompts.rag import prompt_rag

router = APIRouter()


def sse(data: dict) -> str:
    return f"data: {json.dumps(data, ensure_ascii=False)}\n\n"


# ✨ MOTOR DE GERAÇÃO PROCEDURAL DE PENSAMENTOS ✨
class GeradorPensamentos:
    @staticmethod
    def intencao(texto, modo):
        assunto = texto[:35] + "..." if len(texto) > 35 else texto
        verbos = ["A descodificar", "A analisar", "A mapear", "A refletir sobre", "A destrinçar"]
        focos  = ["o núcleo da tua pergunta", f"a intenção por trás de '{assunto}'", f"o que procuras saber sobre '{assunto}'"]

        if modo == "quiz":    return random.choice(["🎮 A desenhar o esqueleto do quiz...", f"🎲 A planear um desafio sobre '{assunto}'..."])
        if modo == "feynman": return random.choice([f"🧠 Como posso simplificar '{assunto}'?", "👶 A preparar uma explicação à prova de idiotas..."])

        return f"🧩 {random.choice(verbos)} {random.choice(focos)}..."

    @staticmethod
    def pesquisa(queries, uc):
        termos     = [q.replace('"', '') for q in queries][:2]
        str_termos = " e ".join(termos)
        return random.choice([
            f"🔍 Vou procurar por '{str_termos}' nos arquivos de {uc}.",
            f"📚 A cruzar os conceitos de '{str_termos}' com o material da cadeira.",
            f"📡 A varrer a base vetorial de {uc} em busca de '{termos[0]}'.",
            f"🎯 O meu foco agora é encontrar referências a '{str_termos}'.",
        ])

    @staticmethod
    def leitura(ficheiros, num_blocos):
        nomes = ", ".join(list(ficheiros)[:3])
        if len(ficheiros) > 3: nomes += f" e mais {len(ficheiros)-3} ficheiros"
        return random.choice([
            f"📄 Encontrei {num_blocos} blocos de texto promissores em: {nomes}.",
            f"📖 A folhear {num_blocos} parágrafos de {nomes}...",
            f"📑 Extraí {num_blocos} excertos diretos de {nomes}. A ler...",
            f"📂 A analisar a informação recolhida de {nomes} ({num_blocos} fragmentos).",
        ])

    @staticmethod
    def avaliacao(num_finais, modo):
        if num_finais == 0:
            return random.choice([
                "⚠️ Não encontrei referências exatas nos PDFs. Vou usar o meu conhecimento geral.",
                "🏜️ Os apontamentos não falam sobre isto. A transitar para a minha base de dados global.",
            ])
        return random.choice([
            f"⚖️ Filtrei o ruído. Fiquei com os {num_finais} parágrafos mais perfeitos.",
            f"🎯 Contexto isolado! A basear a minha resposta em {num_finais} fontes cruciais.",
            f"💎 De tudo o que li, separei {num_finais} excertos cirúrgicos. A redigir...",
            f"🧠 O modelo Cross-Encoder escolheu os {num_finais} melhores fragmentos.",
        ])


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
        raise HTTPException(status_code=403, detail="Acesso negado.")

    if not thread_id or thread_id == "string":
        thread_id = str(uuid.uuid4())
    else:
        try:
            uuid.UUID(str(thread_id))
        except ValueError:
            thread_id = str(uuid.uuid4())

    loop     = asyncio.get_running_loop()
    uc_limpa = limpar_nome_uc(uc.value)
    uc_nome  = uc.value

    vs = await get_vector_store(uc_limpa)
    if vs is None:
        raise HTTPException(status_code=400, detail=f"Ainda não existem documentos para a UC: {uc_nome}.")

    texto_final_aluno = sanitizar_input(texto)
    tem_imagem        = False

    if imagem:
        texto_final_aluno, tem_imagem = await processar_ocr(imagem, settings.max_image_mb, texto_final_aluno)

    query_emb = await loop.run_in_executor(executor, embeddings_model.embed_query, texto_final_aluno)

    # ⚡ Procura no Redis
    resposta_cacheada = await procurar_cache_redis(uc_limpa, query_emb, settings.semantic_cache_threshold)

    if resposta_cacheada:
        async def cached_generator():
            yield sse({"status": "sucesso", "thread_id": thread_id, "sem_contexto": resposta_cacheada.get("sem_contexto", False)})
            yield sse({"status_msg": "⚡ Déjà vu! Fui buscar esta resposta à memória de longo prazo."})
            yield sse({"chunk": resposta_cacheada["resposta_stu"]})
            yield "data: [DONE]\n\n"
        return StreamingResponse(cached_generator(), media_type="text/event-stream")

    try:
        mensagens_historico = json.loads(historico)
    except Exception:
        mensagens_historico = []

    async def event_generator():
        yield sse({"status": "sucesso", "thread_id": thread_id, "sem_contexto": False})

        # 1. INTENÇÃO DINÂMICA
        yield sse({"status_msg": GeradorPensamentos.intencao(texto_final_aluno, preferencia.value)})
        modo_resumo      = e_pergunta_de_resumo(texto_final_aluno)
        queries_pesquisa = await expandir_queries(texto_final_aluno, tem_imagem, modo_resumo, uc_nome, mensagens_historico, thread_id, request)
        await asyncio.sleep(0.4)

        # 2. PESQUISA VETORIAL DINÂMICA
        yield sse({"status_msg": GeradorPensamentos.pesquisa(queries_pesquisa, uc_nome)})
        bm25                = await get_bm25_retriever(uc_limpa)
        docs_hibridos, _    = await executar_retrieval(queries_pesquisa, vs, bm25, settings.faiss_k * 2 if modo_resumo else settings.faiss_k)
        await asyncio.sleep(0.5)

        # 3. LEITURA DOS FICHEIROS DINÂMICA
        ficheiros_apanhados = set()
        for doc in docs_hibridos:
            primeira_linha = doc.page_content.split('\n')[0]
            if "[CABEÇALHO FONTE:" in primeira_linha:
                partes = primeira_linha.replace("[CABEÇALHO FONTE: ", "").replace("]", "").split(":")
                if len(partes) >= 2:
                    ficheiros_apanhados.add(partes[0].strip())

        if ficheiros_apanhados:
            yield sse({"status_msg": GeradorPensamentos.leitura(ficheiros_apanhados, len(docs_hibridos))})
        else:
            yield sse({"status_msg": f"📚 A procurar {len(docs_hibridos)} blocos cegos na base vetorial..."})
        await asyncio.sleep(0.6)

        # 4. RE-RANKING DINÂMICO
        textos_finais, _, _ = await executar_reranking(docs_hibridos, texto_final_aluno, settings.final_k * 3 if modo_resumo else settings.final_k * 2)
        flag_sem_contexto   = not textos_finais and not modo_resumo
        yield sse({"sem_contexto": flag_sem_contexto})

        yield sse({"status_msg": GeradorPensamentos.avaliacao(len(textos_finais), preferencia.value)})
        await asyncio.sleep(0.5)

        contexto_recuperado = (
            "Responde com conhecimento geral e diz que a informação não constava nos documentos."
            if flag_sem_contexto
            else "\n\n---\n\n".join(textos_finais)
        )

        prompt = prompt_rag(
            uc_nome,
            contexto_recuperado,
            texto_final_aluno,
            preferencia,
            tem_imagem,
            modo_resumo,
            mensagens_historico,
        )

        full_response = ""
        houve_erro    = False
        from services.iaedu import chamar_iaedu_stream

        try:
            async for chunk in chamar_iaedu_stream(prompt, thread_id, request):
                full_response += chunk
                yield sse({"chunk": chunk})
        except Exception as exc:
            logger.error("Erro na IAedu Stream: %s", exc)
            houve_erro = True
            yield sse({"chunk": "\n\n❌ Erro de comunicação com o servidor de Inteligência Artificial."})

        yield "data: [DONE]\n\n"

        if preferencia == PreferenciaEnum.plano and "[CALENDARIO]" in full_response:
            disparar_background(processar_calendario(full_response, uc_nome, executor, loop), "calendario_background")

        # ⚡ Guarda no Redis em background (não atrasa o retorno ao aluno)
        if not houve_erro and full_response and len(full_response.strip()) > 20:
            resposta_final = {
                "status":            "sucesso",
                "thread_id":         thread_id,
                "pergunta_original": texto_final_aluno,
                "query_expandida":   queries_pesquisa,
                "resposta_stu":      full_response,
                "sem_contexto":      flag_sem_contexto,
                "fontes_consultadas": list(set(re.findall(r"\[(.*?:\d+)\]", full_response))),
            }
            disparar_background(
                guardar_cache_redis(uc_limpa, query_emb, resposta_final, settings.redis_cache_ttl_dias),
                "guardar_cache_redis",
            )

    return StreamingResponse(event_generator(), media_type="text/event-stream")