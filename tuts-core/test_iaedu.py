import httpx
import asyncio
import json
import sys
sys.path.append('.')
from config import settings

async def testar():
    url = f"https://api.iaedu.pt/agent-chat/api/v1/agent/{settings.iaedu_agent_id}/stream"
    headers = {"x-api-key": settings.iaedu_api_key}
    form_data = {
        "channel_id": settings.iaedu_channel_id,
        "thread_id": "teste-123",
        "user_info": "{}",
        "message": "Diz apenas: ola"
    }
    
    print(f"URL: {url}")
    print(f"Agent ID: {settings.iaedu_agent_id}")
    print("---")
    
    async with httpx.AsyncClient() as client:
        async with client.stream("POST", url, headers=headers, data=form_data, timeout=30.0) as r:
            print(f"Status: {r.status_code}")
            async for linha in r.aiter_lines():
                if linha.strip():
                    print(repr(linha))

asyncio.run(testar())
