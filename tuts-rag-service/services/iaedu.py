import json
import httpx
import asyncio
import os
from typing import AsyncIterator
from tenacity import retry, stop_after_attempt, wait_exponential
from fastapi import Request
from config import settings, logger

# ---------------------------------------------------------------------------
# Configuração
# ---------------------------------------------------------------------------

# Timeout: 10s para conectar, 120s para ler (a IA pode demorar a responder)
_TIMEOUT = httpx.Timeout(10.0, read=120.0)

# Efeito máquina de escrever — configurável via variáveis de ambiente
TYPEWRITER_DELAY: float        = float(os.getenv("TYPEWRITER_DELAY_MS",      "30")) / 1000
TYPEWRITER_FAST_DELAY: float   = float(os.getenv("TYPEWRITER_FAST_DELAY_MS",  "5")) / 1000
TYPEWRITER_FAST_THRESHOLD: int = int(os.getenv("TYPEWRITER_FAST_THRESHOLD",  "15"))


# ---------------------------------------------------------------------------
# Excepções
# ---------------------------------------------------------------------------

class IAEduEmptyResponseError(Exception):
    """Levantada quando a API da IAEdu devolve uma resposta vazia ou irreconhecível."""

class IAEduAPIError(Exception):
    """Levantada quando a API da IAEdu devolve explicitamente um erro (ex: rate limit)."""


# ---------------------------------------------------------------------------
# Formato real da API IAEdu (confirmado por observação dos logs)
#
#   type: "start"   -> sinal de inicio, content: "Processing"       -- ignorar
#   type: "token"   -> chunk incremental, content: "<texto>"        -- usar no stream
#   type: "message" -> mensagem final acumulada,                    -- usar na funcao classica
#                      content: {"type": "ai", "content": "<texto completo>"}
#   type: "done"    -> sinal de fim, content: "<run_id>"            -- ignorar
#   type: "error"   -> erro explicito, content: "<mensagem>"        -- lancar excepcao
# ---------------------------------------------------------------------------

# ---------------------------------------------------------------------------
# Helpers internos
# ---------------------------------------------------------------------------

def _build_request_params(prompt: str, thread_id: str, user_info: dict | None = None) -> dict:
    """Constrói os parâmetros comuns do request HTTP."""
    return {
        "url": (
            f"https://api.iaedu.pt/agent-chat/api/v1/agent"
            f"/{settings.iaedu_agent_id}/stream"
        ),
        "headers": {"x-api-key": settings.iaedu_api_key},
        "data": {
            "channel_id": settings.iaedu_channel_id,
            "thread_id":  thread_id,
            "user_info":  json.dumps(user_info or {}),
            "message":    prompt,
        },
    }


async def _iter_sse_payloads(lines: AsyncIterator[str]) -> AsyncIterator[dict]:
    """
    Camada base: consome linhas SSE brutas e emite dicts JSON válidos.

    Trata:
      - linhas vazias e [DONE]
      - prefixo "data: "
      - erros de JSON (ignorados com warning)
      - type=error  -> lança IAEduAPIError imediatamente
      - type=start / type=done -> ignorados silenciosamente
    """
    async for linha in lines:
        linha = linha.strip()

        if not linha:
            continue

        if linha.startswith("data: "):
            linha = linha[6:].strip()

        if linha == "[DONE]":
            continue

        try:
            dados = json.loads(linha)
        except json.JSONDecodeError:
            logger.warning("Linha SSE não é JSON válido: '%s'", linha[:80])
            continue

        tipo = dados.get("type")

        if tipo == "error":
            raise IAEduAPIError(dados.get("content", "Erro desconhecido da API IAEdu"))

        if tipo in ("start", "done"):
            continue

        yield dados


async def _iter_tokens(lines: AsyncIterator[str]) -> AsyncIterator[str]:
    """
    Emite os chunks de texto incrementais (type=token).
    Usado pela função de streaming — cada evento contém apenas a fatia nova.
    """
    async for dados in _iter_sse_payloads(lines):
        if dados.get("type") == "token":
            conteudo = dados.get("content")
            if isinstance(conteudo, str) and conteudo:
                yield conteudo


async def _iter_mensagem_final(lines: AsyncIterator[str]) -> AsyncIterator[str]:
    """
    Emite o texto da mensagem final acumulada (type=message).
    Usado pela função clássica — contém o texto completo da resposta.
    """
    async for dados in _iter_sse_payloads(lines):
        if dados.get("type") == "message":
            texto = dados.get("content", {}).get("content")
            if texto:
                yield texto


# ---------------------------------------------------------------------------
# API pública
# ---------------------------------------------------------------------------

@retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=1, min=1, max=4), reraise=True)
async def chamar_iaedu(
    prompt: str,
    thread_id: str,
    request: Request,
    user_info: dict | None = None,
) -> str:
    """
    Chamada clássica (não-streaming) à API da IAEdu.

    Usa o evento type=message que contém o texto completo da resposta.
    Usada em tarefas de background (ex: expansão de queries, metadados).

    Lança IAEduEmptyResponseError se a resposta for vazia.
    Tem retry automático até 3 tentativas com backoff exponencial.
    """
    client = request.app.state.http_client
    params = _build_request_params(prompt, thread_id, user_info)

    resposta_api = await client.post(
        params["url"],
        headers=params["headers"],
        data=params["data"],
        timeout=_TIMEOUT,
    )
    resposta_api.raise_for_status()

    async def _linhas_sincronas() -> AsyncIterator[str]:
        for linha in resposta_api.text.splitlines():
            yield linha

    texto_final = ""

    async for texto in _iter_mensagem_final(_linhas_sincronas()):
        texto_final = texto  # type=message é sempre o texto acumulado completo

    if not texto_final.strip():
        logger.error("Resposta vazia da IAEdu (clássica). thread_id=%s", thread_id)
        raise IAEduEmptyResponseError(f"Resposta vazia para thread_id={thread_id}")

    return texto_final


async def chamar_iaedu_stream(
    prompt: str,
    thread_id: str,
    request: Request,
    user_info: dict | None = None,
) -> AsyncIterator[str]:
    """
    Chamada em streaming à API da IAEdu.

    Usa os eventos type=token que chegam incrementalmente.
    Emite tokens individualmente com um atraso configurável para criar
    o efeito máquina de escrever no Vue.js.

    Lança IAEduEmptyResponseError se nenhum token for recebido.
    """
    client = request.app.state.http_client
    params = _build_request_params(prompt, thread_id, user_info)

    async with client.stream(
        "POST",
        params["url"],
        headers=params["headers"],
        data=params["data"],
        timeout=_TIMEOUT,
    ) as response:
        response.raise_for_status()

        emitiu_algo = False

        async for token in _iter_tokens(response.aiter_lines()):
            emitiu_algo = True

            # Tokens são já incrementais — emitir directamente, palavra a palavra
            palavras = token.split(" ")

            # Chunks grandes aceleram o delay para não bloquear o render no cliente
            delay = (
                TYPEWRITER_DELAY
                if len(palavras) <= TYPEWRITER_FAST_THRESHOLD
                else TYPEWRITER_FAST_DELAY
            )

            for i, palavra in enumerate(palavras):
                yield palavra + (" " if i < len(palavras) - 1 else "")
                await asyncio.sleep(delay)

        if not emitiu_algo:
            logger.error("Stream vazio da IAEdu. thread_id=%s", thread_id)
            raise IAEduEmptyResponseError(f"Stream vazio para thread_id={thread_id}")