import json
import httpx
from tenacity import retry, stop_after_attempt, wait_exponential
from fastapi import Request
from config import settings, logger

# Timeout separado: 10s para conectar, 120s para ler (a IA pode demorar a responder)
_TIMEOUT = httpx.Timeout(10.0, read=120.0)


# 1. Função Clássica (Usada em background, ex: expandir queries)
@retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=1, min=1, max=4), reraise=True)
async def chamar_iaedu(prompt: str, thread_id: str, request: Request) -> str:
    client = request.app.state.http_client
    url = f"https://api.iaedu.pt/agent-chat/api/v1/agent/{settings.iaedu_agent_id}/stream"
    headers = {"x-api-key": settings.iaedu_api_key}
    form_data = {
        "channel_id": settings.iaedu_channel_id,
        "thread_id":  thread_id,
        "user_info":  "{}",
        "message":    prompt,
    }

    resposta_api = await client.post(url, headers=headers, data=form_data, timeout=_TIMEOUT)
    resposta_api.raise_for_status()

    texto_final = ""

    for linha in resposta_api.text.split("\n"):
        linha = linha.strip()
        if not linha:
            continue

        if linha.startswith("data: "):
            linha = linha[6:].strip()

        if linha == "[DONE]":
            continue

        try:
            dados = json.loads(linha)
            if dados.get("type") == "message" and "content" in dados and "content" in dados["content"]:
                texto_atual = dados["content"]["content"]

                # Lógica híbrida — funciona quer a IA envie acumulado ou às fatias
                if texto_atual.startswith(texto_final) and len(texto_final) > 0:
                    texto_final = texto_atual
                else:
                    texto_final += texto_atual
        except json.JSONDecodeError:
            continue

    if not texto_final.strip():
        logger.warning("Resposta IAedu sem conteúdo reconhecível.")

    return texto_final


# 2. Função de Streaming (Usada para escrever em tempo real no Vue.js)
# ✅ SEM @retry — tenacity é incompatível com async generators (quebra silenciosamente)
async def chamar_iaedu_stream(prompt: str, thread_id: str, request: Request):
    client = request.app.state.http_client
    url = f"https://api.iaedu.pt/agent-chat/api/v1/agent/{settings.iaedu_agent_id}/stream"
    headers = {"x-api-key": settings.iaedu_api_key}
    form_data = {
        "channel_id": settings.iaedu_channel_id,
        "thread_id":  thread_id,
        "user_info":  "{}",
        "message":    prompt,
    }

    async with client.stream("POST", url, headers=headers, data=form_data, timeout=_TIMEOUT) as response:
        response.raise_for_status()

        async for linha in response.aiter_lines():
            linha = linha.strip()
            if not linha:
                continue

            if linha.startswith("data: "):
                linha = linha[6:].strip()

            if linha == "[DONE]":
                continue

            try:
                dados = json.loads(linha)
                if (
                    dados.get("type") == "message"
                    and "content" in dados
                    and "content" in dados["content"]
                ):
                    yield dados["content"]["content"]
            except json.JSONDecodeError:
                continue