import json

from fastapi import Request
from tenacity import RetryError, retry, stop_after_attempt, wait_exponential

from config import logger, settings


@retry(
    stop=stop_after_attempt(3),
    wait=wait_exponential(multiplier=1, min=1, max=4),
    reraise=True,
)
async def chamar_iaedu(prompt: str, thread_id: str, request: Request) -> str:
    client = request.app.state.http_client
    url = (
        f"https://api.iaedu.pt/agent-chat/api/v1/agent"
        f"/{settings.iaedu_agent_id}/stream"
    )
    headers = {"x-api-key": settings.iaedu_api_key}
    form_data = {
        "channel_id": settings.iaedu_channel_id,
        "thread_id": thread_id,
        "user_info": "{}",
        "message": prompt,
    }

    resposta_api = await client.post(
        url, headers=headers, data=form_data, timeout=60.0
    )
    resposta_api.raise_for_status()

    for linha in resposta_api.text.split("\n\n"):
        if not linha.strip():
            continue
        try:
            dados = json.loads(linha)
            if (
                dados.get("type") == "message"
                and "content" in dados
                and "content" in dados["content"]
            ):
                return dados["content"]["content"]
        except json.JSONDecodeError:
            continue

    logger.warning("Resposta IAedu sem conteúdo reconhecível.")
    return ""