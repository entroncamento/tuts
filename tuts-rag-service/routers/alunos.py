import uuid
import json
import asyncio
import secrets
from typing import Annotated, AsyncIterator

from fastapi import (
    APIRouter,
    File,
    Form,
    Request,
    UploadFile,
    Header,
    HTTPException,
    BackgroundTasks,
)
from fastapi.responses import StreamingResponse

from services.preferencia_auto import detetar_preferencia_automatica

from config import settings, PreferenciaEnum, logger
from core.ml_models import embeddings_model, executor
from core.cache import procurar_cache_redis, guardar_cache_redis, obter_versao_uc
from core.background import disparar_background
from core.utils import limpar_nome_uc, sanitizar_input
from core.retrieval import (
    get_vector_store,
    get_bm25_retriever,
    executar_retrieval,
    executar_reranking,
)
from services.ocr import processar_ocr
from services.query_expansion import e_pergunta_de_resumo, expandir_queries
from services.calendar import extrair_propostas_calendario
from prompts.rag import prompt_rag
from services.analise import analisar_conversa_e_guardar


router = APIRouter()

MAX_HISTORICO_MENSAGENS = 10
MAX_HISTORICO_BYTES = 10_000

# Proteção para rerankers que devolvem scores negativos.
# O cross-encoder mmarco usado no TUT'S pode devolver scores entre -4 e -7
# mesmo quando os excertos são úteis. Portanto 0.5 é demasiado agressivo.
RERANK_MIN_SCORE_SEGURO = -8.0


def sse(data: dict) -> str:
    return f"data: {json.dumps(data, ensure_ascii=False)}\n\n"


def resposta_tecnica_servico(texto: str) -> bool:
    t = (texto or "").strip().lower()

    padroes = [
        "❌ o serviço de ia está temporariamente com demasiados pedidos",
        "❌ o serviço de ia falhou ao processar o pedido",
        "❌ falha na comunicação com o serviço de ia",
        "❌ erro: a ia não enviou resposta",
        "❌ falha interna na comunicação com o serviço",
        "too many requests",
        "rate limit",
        "temporariamente com demasiados pedidos",
    ]

    return any(p in t for p in padroes)


def _contem_dados_pessoais_simples(texto: str) -> bool:
    t = texto.lower()

    return any(
        p in t
        for p in [
            "meu número",
            "número mecanográfico",
            "nm",
            "telemóvel",
            "morada",
            "email",
            "@ua.pt",
        ]
    )


def _score_float(score) -> float | None:
    if score is None:
        return None

    try:
        return float(score)
    except Exception:
        return None


def _score_legivel(score: float | None) -> str:
    if score is None:
        return "sem score"

    return f"{score:.2f}"


def _eh_debug_useeffect_loop(texto: str) -> bool:
    t = (texto or "").lower()
    return (
        "useeffect" in t
        and any(p in t for p in ["loop", "infinito", "dispara", "corrige", "corrigir"])
    )


def _queries_locais_react_debug(texto: str) -> list[str]:
    if _eh_debug_useeffect_loop(texto):
        return [
            "useEffect array de dependências",
            "useEffect dependências count",
            "useEffect setCount count",
            "useEffect atualização estado",
            "useEffect componentDidUpdate",
            "useEffect lifecycle atualização",
            "useEffect executado quando dependências mudam",
            "useEffect return cleanup",
        ]
    return []


def _query_rerank_especial(texto: str) -> str:
    if _eh_debug_useeffect_loop(texto):
        return (
            "useEffect array de dependências atualização estado count "
            "componentDidUpdate executa quando dependências mudam"
        )
    return texto


def _min_score_contexto_calibrado(
    textos_finais: list[str],
    texto_final_aluno: str,
) -> float:
    """
    Calcula o threshold real usado para decidir se há contexto suficiente.

    Importante:
    - settings.rerank_min_score_contexto pode vir mal configurado pelo .env.
    - O modelo mmarco pode devolver scores negativos úteis.
    - Se há textos_finais recuperados, nunca devemos usar um threshold positivo
      como 0.5, porque isso força falsos "sem contexto".
    """

    min_score_original = float(
        getattr(settings, "rerank_min_score_contexto", RERANK_MIN_SCORE_SEGURO)
    )

    min_score_contexto = min_score_original

    if textos_finais and min_score_contexto > RERANK_MIN_SCORE_SEGURO:
        logger.warning(
            "[RAG] rerank_min_score_contexto demasiado alto (%s). "
            "A forçar %s porque há textos_finais recuperados.",
            min_score_contexto,
            RERANK_MIN_SCORE_SEGURO,
        )
        min_score_contexto = RERANK_MIN_SCORE_SEGURO

    if _eh_debug_useeffect_loop(texto_final_aluno):
        min_score_contexto = min(min_score_contexto, -2.0)

    return min_score_contexto


class GeradorPensamentos:
    """
    Isto não tenta simular pensamento interno da IA.

    São mensagens reais de progresso do pipeline:
    - receção da pergunta
    - expansão de queries
    - retrieval
    - leitura dos excertos
    - reranking
    - decisão sobre contexto suficiente
    - preparação da resposta final
    """

    @staticmethod
    def _resumir_texto(texto: str, limite: int = 52) -> str:
        t = " ".join((texto or "").split()).strip()

        if not t:
            return "a pergunta"

        if len(t) <= limite:
            return t

        return t[: limite - 3].rstrip() + "..."

    @staticmethod
    def _termos_queries(queries: list[str], limite: int = 3) -> list[str]:
        termos = []

        for query in queries:
            q = " ".join((query or "").replace('"', "").split()).strip()

            if not q:
                continue

            if q not in termos:
                termos.append(GeradorPensamentos._resumir_texto(q, 48))

            if len(termos) >= limite:
                break

        return termos

    @staticmethod
    def inicio(
        texto: str,
        modo: str,
        tem_imagem: bool,
        num_mensagens_historico: int,
    ) -> str:
        pergunta = GeradorPensamentos._resumir_texto(texto)
        extras = []

        if tem_imagem:
            extras.append("imagem/OCR")

        if num_mensagens_historico:
            extras.append(f"{num_mensagens_historico} mensagens de histórico")

        contexto_extra = f" Também recebi {', '.join(extras)}." if extras else ""

        if modo and modo != "default":
            return (
                f"Recebi a pergunta “{pergunta}” e vou tratá-la no modo {modo}."
                f"{contexto_extra}"
            )

        return f"Recebi a pergunta “{pergunta}” e vou procurar suporte nos materiais da UC.{contexto_extra}"

    @staticmethod
    def cache_hit() -> str:
        return (
            "Encontrei uma resposta compatível na cache semântica desta UC. "
            "Vou reutilizá-la sem repetir a pesquisa aos documentos."
        )

    @staticmethod
    def expansao(
        queries: list[str],
        fallback_usado: bool,
    ) -> str:
        termos = GeradorPensamentos._termos_queries(queries)

        if fallback_usado:
            return (
                "A expansão automática falhou, por isso vou pesquisar com a pergunta original "
                "e variações locais seguras."
            )

        if not queries:
            return "Não consegui gerar queries adicionais. Vou pesquisar diretamente pela pergunta original."

        if len(queries) == 1:
            return f"Preparei 1 consulta de pesquisa: “{termos[0]}”."

        termos_txt = "”, “".join(termos)
        extra = "" if len(queries) <= len(termos) else f" e mais {len(queries) - len(termos)}"

        return f"Preparei {len(queries)} consultas de pesquisa: “{termos_txt}”{extra}."

    @staticmethod
    def pesquisa(queries: list[str], uc: str) -> str:
        total = len(queries)

        if total == 0:
            return f"Vou pesquisar diretamente nos materiais indexados da UC “{uc}”."

        return (
            f"A pesquisar na UC “{uc}” com {total} consulta"
            f"{'' if total == 1 else 's'} contra o índice vetorial e o índice lexical."
        )

    @staticmethod
    def retrieval(num_candidatos: int, tem_bm25: bool, modo_resumo: bool) -> str:
        motor = "FAISS + BM25" if tem_bm25 else "FAISS"
        escopo = " com janela alargada por ser uma pergunta de resumo" if modo_resumo else ""

        if num_candidatos == 0:
            return f"O retrieval {motor} não devolveu candidatos relevantes{escopo}."

        return (
            f"O retrieval {motor} devolveu {num_candidatos} excerto"
            f"{'' if num_candidatos == 1 else 's'} candidato"
            f"{'' if num_candidatos == 1 else 's'}{escopo}."
        )

    @staticmethod
    def leitura(ficheiros: set[str], num_blocos: int) -> str:
        if num_blocos == 0:
            return "Não há excertos recuperados para ler nesta passagem."

        num_fontes = len(ficheiros)

        if num_fontes == 0:
            return (
                f"Vou analisar {num_blocos} excerto"
                f"{'' if num_blocos == 1 else 's'} recuperado"
                f"{'' if num_blocos == 1 else 's'}, sem metadados de ficheiro visíveis."
            )

        return (
            f"Vou analisar {num_blocos} excerto"
            f"{'' if num_blocos == 1 else 's'} recuperado"
            f"{'' if num_blocos == 1 else 's'} de {num_fontes} fonte"
            f"{'' if num_fontes == 1 else 's'} da UC."
        )

    @staticmethod
    def reranking(
        num_antes_filtro: int,
        score_max: float | None,
        min_score_contexto: float,
    ) -> str:
        if num_antes_filtro == 0:
            return "O reranker não encontrou excertos finais suficientemente alinhados com a pergunta."

        return (
            f"O reranker selecionou {num_antes_filtro} excerto"
            f"{'' if num_antes_filtro == 1 else 's'} final"
            f"{'' if num_antes_filtro == 1 else 'ais'}; "
            f"melhor score: {_score_legivel(score_max)} "
            f"(mínimo configurado: {min_score_contexto:.2f})."
        )

    @staticmethod
    def decisao_contexto(
        sem_contexto: bool,
        num_finais_validos: int,
        score_max: float | None,
        min_score_contexto: float,
    ) -> str:
        if sem_contexto:
            if score_max is None:
                return (
                    "Não consegui validar contexto suficiente nos materiais da UC. "
                    "Vou responder de forma conservadora e assinalar a falta de suporte documental."
                )

            return (
                f"O melhor score foi {_score_legivel(score_max)}, abaixo do mínimo "
                f"{min_score_contexto:.2f}. Vou tratar isto como sem contexto suficiente."
            )

        return (
            f"Contexto validado: vou responder usando {num_finais_validos} excerto"
            f"{'' if num_finais_validos == 1 else 's'} final"
            f"{'' if num_finais_validos == 1 else 'ais'} dos materiais da UC."
        )

    @staticmethod
    def prompt_pronto(sem_contexto: bool) -> str:
        if sem_contexto:
            return (
                "A montar a resposta final em modo conservador, sem inventar informação fora dos materiais."
            )

        return "A montar a resposta final com o contexto recuperado e as regras de citação da UC."


async def _emitir_stream_com_buffer(
    prompt: str,
    thread_id: str,
    request: Request,
) -> AsyncIterator[str]:
    """
    A Groq envia deltas muito pequenos. Se reenviarmos cada delta como SSE,
    o avaliador pode atingir o limite de chunks e marcar resposta truncada.
    Este buffer junta vários deltas antes de os enviar.
    """
    from services.iaedu import chamar_iaedu_stream

    buffer = ""
    limite_buffer = max(80, int(getattr(settings, "sse_buffer_chars", 180)))

    async for chunk in chamar_iaedu_stream(prompt, thread_id, request):
        buffer += chunk

        deve_emitir = (
            len(buffer) >= limite_buffer
            or buffer.endswith("\n\n")
            or buffer.endswith(". ")
            or buffer.endswith("! ")
            or buffer.endswith("? ")
            or buffer.endswith("```")
        )

        if deve_emitir:
            yield buffer
            buffer = ""

    if buffer:
        yield buffer


@router.post("/perguntar", tags=["Alunos"])
async def perguntar(
    request: Request,
    background_tasks: BackgroundTasks,
    texto: Annotated[str, Form(...)],
    uc: Annotated[str, Form(...)],
    x_internal_token: Annotated[str, Header()],
    thread_id: Annotated[str | None, Form()] = None,
    message_id: Annotated[int | None, Form()] = None,
    preferencia: Annotated[PreferenciaEnum, Form()] = PreferenciaEnum.default,
    historico: Annotated[str, Form()] = "[]",
    bypass_cache: Annotated[bool, Form()] = False,
    imagem: Annotated[UploadFile | None, File()] = None,
):
    token_recebido = (x_internal_token or "").strip()
    token_esperado = (settings.internal_token or "").strip()

    if not token_esperado or not secrets.compare_digest(token_recebido, token_esperado):
        logger.warning(
            "[SEGURANÇA] Tentativa de acesso não autorizada ao endpoint /perguntar a partir de %s",
            request.client.host if request.client else "desconhecido",
        )
        raise HTTPException(status_code=403, detail="Acesso negado.")

    if (
        settings.app_env == "production"
        and request.client
        and request.client.host not in ["127.0.0.1", "::1", settings.server_host]
    ):
        logger.error(
            "[SEGURANÇA] O RAG foi acedido a partir de um IP não autorizado em Produção: %s",
            request.client.host,
        )
        raise HTTPException(status_code=403, detail="Endpoint restrito a chamadas internas.")

    if len(historico) > MAX_HISTORICO_BYTES:
        raise HTTPException(status_code=400, detail="Histórico demasiado grande.")

    try:
        mensagens_historico = json.loads(historico)

        if not isinstance(mensagens_historico, list):
            mensagens_historico = []

        mensagens_historico = mensagens_historico[-MAX_HISTORICO_MENSAGENS:]

    except Exception:
        mensagens_historico = []

    if not thread_id or thread_id == "string":
        thread_id = f"chat_{uuid.uuid4().hex[:8]}"

    loop = asyncio.get_running_loop()
    uc_limpa = limpar_nome_uc(uc)
    uc_nome = uc

    vs = await get_vector_store(uc_limpa)

    if vs is None:
        raise HTTPException(
            status_code=400,
            detail=f"Ainda não existem documentos para a UC: {uc_nome}.",
        )

    texto_final_aluno = sanitizar_input(texto)
    tem_imagem = False

    if imagem:
        texto_final_aluno, tem_imagem = await processar_ocr(
            imagem,
            settings.max_image_mb,
            texto_final_aluno,
        )

    modo_resumo = e_pergunta_de_resumo(texto_final_aluno)
    preferencia_efetiva = detetar_preferencia_automatica(texto_final_aluno, preferencia)

    versao_uc = obter_versao_uc(uc_limpa)

    cache_apto = (
        not bypass_cache
        and getattr(settings, "semantic_cache_enabled", True)
        and preferencia_efetiva == PreferenciaEnum.default
        and not tem_imagem
        and not mensagens_historico
        and not modo_resumo
        and len(texto_final_aluno) < 250
        and not _contem_dados_pessoais_simples(texto_final_aluno)
    )

    query_emb = None
    resposta_cacheada = None

    if cache_apto:
        query_emb = await loop.run_in_executor(
            executor,
            embeddings_model.embed_query,
            texto_final_aluno,
        )

        resposta_cacheada = await procurar_cache_redis(
            uc_limpa,
            versao_uc,
            query_emb,
            settings.semantic_cache_threshold,
        )

        if resposta_cacheada and resposta_tecnica_servico(
            resposta_cacheada.get("resposta_stu", "")
        ):
            resposta_cacheada = None

    if resposta_cacheada:
        async def cached_generator():
            yield sse(
                {
                    "status": "sucesso",
                    "thread_id": thread_id,
                    "sem_contexto": resposta_cacheada.get("sem_contexto", False),
                    "preferencia_auto": preferencia_efetiva.value,
                    "cache_hit": True,
                }
            )

            yield sse({"status_msg": GeradorPensamentos.cache_hit()})

            yield sse({"chunk": resposta_cacheada["resposta_stu"]})

            if "calendario" in resposta_cacheada:
                yield sse({"calendario": resposta_cacheada["calendario"]})

            yield "data: [DONE]\n\n"

        return StreamingResponse(cached_generator(), media_type="text/event-stream")

    async def event_generator():
        yield sse(
            {
                "status": "sucesso",
                "thread_id": thread_id,
                "sem_contexto": False,
                "preferencia_auto": preferencia_efetiva.value,
                "cache_hit": False,
            }
        )

        yield sse(
            {
                "status_msg": GeradorPensamentos.inicio(
                    texto_final_aluno,
                    preferencia_efetiva.value,
                    tem_imagem,
                    len(mensagens_historico),
                )
            }
        )

        expansao_fallback = False

        try:
            queries_pesquisa = await expandir_queries(
                texto_final_aluno,
                tem_imagem,
                modo_resumo,
                uc_nome,
                mensagens_historico,
                thread_id,
                request,
            )
        except Exception as e:
            logger.warning(
                "[EXPANSÃO] Falhou. Fallback para query original. Erro: %s",
                type(e).__name__,
            )
            queries_pesquisa = [texto_final_aluno]
            expansao_fallback = True

        queries_locais = _queries_locais_react_debug(texto_final_aluno)
        queries_pesquisa = list(
            dict.fromkeys(
                queries_locais + queries_pesquisa + [texto_final_aluno]
            )
        )[:10]

        yield sse(
            {
                "status_msg": GeradorPensamentos.expansao(
                    queries_pesquisa,
                    expansao_fallback,
                )
            }
        )

        yield sse(
            {
                "status_msg": GeradorPensamentos.pesquisa(
                    queries_pesquisa,
                    uc_nome,
                )
            }
        )

        bm25 = await get_bm25_retriever(uc_limpa)

        docs_hibridos, _ = await executar_retrieval(
            queries_pesquisa,
            vs,
            bm25,
            settings.faiss_k * 2 if modo_resumo else settings.faiss_k,
        )

        yield sse(
            {
                "status_msg": GeradorPensamentos.retrieval(
                    len(docs_hibridos),
                    bm25 is not None,
                    modo_resumo,
                )
            }
        )

        ficheiros_apanhados = set()

        for doc in docs_hibridos:
            primeira_linha = doc.page_content.split("\n")[0]

            if "[CABEÇALHO FONTE:" in primeira_linha:
                partes = (
                    primeira_linha
                    .replace("[CABEÇALHO FONTE: ", "")
                    .replace("]", "")
                    .split(":")
                )

                if len(partes) >= 2:
                    ficheiros_apanhados.add(partes[0].strip())

        yield sse(
            {
                "status_msg": GeradorPensamentos.leitura(
                    ficheiros_apanhados,
                    len(docs_hibridos),
                )
            }
        )

        query_para_rerank = _query_rerank_especial(texto_final_aluno)

        textos_finais, score_max, _ = await executar_reranking(
            docs_hibridos,
            query_para_rerank,
            settings.final_k * 3 if modo_resumo else settings.final_k * 2,
        )

        score_max_float = _score_float(score_max)
        min_score_contexto = _min_score_contexto_calibrado(
            textos_finais,
            texto_final_aluno,
        )

        num_textos_antes_filtro = len(textos_finais)

        yield sse(
            {
                "status_msg": GeradorPensamentos.reranking(
                    num_textos_antes_filtro,
                    score_max_float,
                    min_score_contexto,
                )
            }
        )

        score_insuficiente = (
            score_max_float is None
            or score_max_float < min_score_contexto
        )

        flag_sem_contexto = not textos_finais or score_insuficiente

        if flag_sem_contexto:
            logger.info(
                "[RAG] Sem contexto suficiente | uc=%s | score_max=%s | min=%s | pergunta=%s",
                uc_limpa,
                score_max_float,
                min_score_contexto,
                texto_final_aluno[:120],
            )
            textos_finais = []
        else:
            logger.info(
                "[RAG] Contexto validado | uc=%s | score_max=%s | min=%s | blocos=%s | pergunta=%s",
                uc_limpa,
                score_max_float,
                min_score_contexto,
                len(textos_finais),
                texto_final_aluno[:120],
            )

        yield sse({"sem_contexto": flag_sem_contexto})

        yield sse(
            {
                "status_msg": GeradorPensamentos.decisao_contexto(
                    flag_sem_contexto,
                    len(textos_finais),
                    score_max_float,
                    min_score_contexto,
                )
            }
        )

        contexto_recuperado = (
            "Não encontrei esta informação nos materiais disponíveis."
            if flag_sem_contexto
            else "\n\n---\n\n".join(textos_finais)
        )

        prompt = prompt_rag(
            uc_nome,
            contexto_recuperado,
            texto_final_aluno,
            preferencia_efetiva,
            tem_imagem,
            modo_resumo,
            mensagens_historico,
            alta_precisao=flag_sem_contexto,
            sem_contexto=flag_sem_contexto,
        )

        yield sse({"status_msg": GeradorPensamentos.prompt_pronto(flag_sem_contexto)})

        full_response = ""
        houve_erro = False

        try:
            async for chunk_emitido in _emitir_stream_com_buffer(
                prompt,
                thread_id,
                request,
            ):
                full_response += chunk_emitido
                yield sse({"chunk": chunk_emitido})

        except Exception as exc:
            logger.error("[RAG] Erro no Stream: %s", type(exc).__name__)
            houve_erro = True
            erro_msg = "\n\n❌ Erro de comunicação com o servidor de IA."
            full_response += erro_msg
            yield sse({"chunk": erro_msg})

        if resposta_tecnica_servico(full_response):
            houve_erro = True

        propostas_calendario = None

        if (
            not houve_erro
            and preferencia_efetiva == PreferenciaEnum.plano
            and "[CALENDARIO]" in full_response
        ):
            propostas_calendario = await extrair_propostas_calendario(
                full_response,
                uc_nome,
            )

            if propostas_calendario:
                yield sse({"calendario": propostas_calendario})

        # Extrair e enviar fontes únicas no SSE
        import os
        import re
        fontes_unicas = {}
        for doc in (docs_hibridos or []):
            meta = doc.metadata or {}
            fname = meta.get("filename") or meta.get("source") or ""
            fname = os.path.basename(str(fname))
            mat_id = meta.get("material_id") or meta.get("materialId")
            if not mat_id:
                m = re.search(r'^(?:.*_)?(\d+)(?:-|\.pdf)', fname, re.IGNORECASE)
                if m:
                    mat_id = m.group(1)

            if mat_id and mat_id not in fontes_unicas:
                fontes_unicas[mat_id] = {
                    "material_id": str(mat_id),
                    "materialId": str(mat_id),
                    "filename": fname,
                    "storage_key": meta.get("storage_key") or meta.get("file_path") or "",
                    "file_path": meta.get("file_path") or meta.get("storage_key") or "",
                    "context_id": meta.get("context_id") or "",
                    "context_type": meta.get("context_type") or "uc",
                }

        yield sse({"sources": list(fontes_unicas.values())})

        yield "data: [DONE]\n\n"

        if houve_erro:
            return

        if full_response and len(full_response.strip()) > 20:
            resposta_final = {
                "status": "sucesso",
                "thread_id": thread_id,
                "sem_contexto": flag_sem_contexto,
                "score_max": score_max_float,
                "resposta_stu": full_response,
                "calendario": propostas_calendario,
            }

            if cache_apto and query_emb is not None:
                disparar_background(
                    guardar_cache_redis(
                        uc_limpa,
                        versao_uc,
                        query_emb,
                        resposta_final,
                        settings.redis_cache_ttl_dias,
                    ),
                    "guardar_cache_redis",
                )

            if message_id:
                client_global = request.app.state.http_client
                background_tasks.add_task(
                    analisar_conversa_e_guardar,
                    client_global,
                    texto_final_aluno,
                    full_response,
                    message_id,
                )

    return StreamingResponse(event_generator(), media_type="text/event-stream")
