import json
import httpx
from typing import AsyncIterator
from tenacity import retry, stop_after_attempt, wait_exponential
from fastapi import Request
from config import logger

# ---------------------------------------------------------------------------
# Configuração da Groq Cloud
# ---------------------------------------------------------------------------
from config import GROQ_API_KEY

GROQ_URL = "https://api.groq.com/openai/v1/chat/completions"

# 🧠 MODELOS SEPARADOS!
MODEL_FAST = "llama-3.1-8b-instant"       # Para JSONs e Background (Rápido e direto)
MODEL_CHAT = "llama-3.3-70b-versatile"    # Para falar com o Aluno (Inteligente e direto)

_TIMEOUT = httpx.Timeout(15.0, read=60.0)

class IAEduEmptyResponseError(Exception):
    pass

class IAEduAPIError(Exception):
    pass

def get_groq_headers():
    return {
        "Authorization": f"Bearer {GROQ_API_KEY}",
        "Content-Type": "application/json"
    }

# ---------------------------------------------------------------------------
# API Pública (Groq Wrapper)
# ---------------------------------------------------------------------------

@retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=1, min=1, max=4), reraise=True)
async def chamar_iaedu(
    prompt: str,
    thread_id: str,
    request: Request,
    user_info: dict | None = None,
) -> str:
    """Função rápida usada apenas para processamento de background (Reescrita, Decomposição)"""
    client = request.app.state.http_client
    
    payload = {
        "model": MODEL_FAST,
        "messages": [{"role": "user", "content": prompt}],
        "stream": False,
        "temperature": 0.3
    }

    try:
        resposta_api = await client.post(GROQ_URL, headers=get_groq_headers(), json=payload, timeout=_TIMEOUT)
        resposta_api.raise_for_status()
        
        dados = resposta_api.json()
        texto_final = dados["choices"][0]["message"]["content"]
        
        if not texto_final.strip():
            raise IAEduEmptyResponseError("Resposta vazia da Groq.")
            
        return texto_final
        
    except httpx.HTTPStatusError as e:
        detalhe = e.response.text
        logger.error(f"Erro 400 da Groq (Background). O que eles dizem: {detalhe}")
        raise IAEduAPIError(f"Erro da Groq: {e.response.status_code}")
    except Exception as e:
        logger.error(f"Erro geral na API da Groq: {e}")
        raise IAEduAPIError("Falha na comunicação com a IA na Cloud.")


async def chamar_iaedu_stream(
    prompt: str,
    thread_id: str,
    request: Request,
    user_info: dict | None = None,
) -> AsyncIterator[str]:
    """Função principal usada para responder ao aluno no chat."""
    client = request.app.state.http_client
    
    # ✅ LIMPÁMOS TUDO AQUI! A personalidade do TUT'S agora vem injetada no 'prompt' através do rag.py
    payload = {
        "model": MODEL_CHAT,
        "messages": [
            {"role": "user", "content": prompt} # Passamos apenas o prompt gigante do RAG
        ],
        "stream": True,
        "temperature": 0.6 
    }

    try:
        async with client.stream("POST", GROQ_URL, headers=get_groq_headers(), json=payload, timeout=_TIMEOUT) as response:
            
            # 🔥 LER O ERRO ANTES DA LIGAÇÃO FECHAR!
            if response.status_code != 200:
                await response.aread()
                erro_texto = response.text
                logger.error(f"Groq bloqueou a Stream: {response.status_code} - {erro_texto}")
                yield f"\n\n❌ **A Groq rejeitou o pedido:** `{erro_texto}`"
                return

            emitiu_algo = False

            async for line in response.aiter_lines():
                if not line or not line.startswith("data: "):
                    continue
                
                json_str = line[6:].strip()
                if json_str == "[DONE]":
                    break
                    
                try:
                    dados = json.loads(json_str)
                    delta = dados["choices"][0].get("delta", {})
                    chunk = delta.get("content", "")
                    
                    if chunk:
                        emitiu_algo = True
                        yield chunk
                        
                except json.JSONDecodeError:
                    continue

            if not emitiu_algo:
                logger.error("Stream vazio da Groq.")
                yield "\n\n❌ Erro: A IA não enviou resposta."
                
    except Exception as e:
        logger.error(f"Erro no Stream da Groq: {e}")
        yield f"\n\n❌ Falha na comunicação com a IA: {str(e)}"