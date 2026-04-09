import json
import asyncio
from config import GROQ_API_KEY, logger, settings

# 🔥 CORREÇÃO 1: Recebe o cliente HTTP global (Connection Pooling)
async def analisar_conversa_e_guardar(client, pergunta: str, resposta: str, message_id: int):
    """Corre em background: Lê a conversa, gera o JSON e envia para o Laravel."""
    
    resposta_curta = resposta[:800] 
    
    prompt = f"""
    Analisa esta interação entre um aluno universitário e um tutor virtual:
    Aluno: {pergunta}
    Tutor (resumo): {resposta_curta}
    
    Devolve APENAS um JSON válido com esta estrutura exata:
    {{
        "frustracao": (número inteiro de 1 a 10 avaliando o grau de dúvida/frustração do aluno),
        "topicos": ["topico 1"]
    }}
    """
    
    tentativas = 0
    max_tentativas = 2
    
    while tentativas < max_tentativas:
        try:
            # 🔥 CORREÇÃO 1: Usa o client reutilizável passado por parâmetro
            res_ia = await client.post(
                "https://api.groq.com/openai/v1/chat/completions",
                headers={"Authorization": f"Bearer {GROQ_API_KEY}"},
                json={
                    "model": "llama-3.1-8b-instant",
                    "messages": [{"role": "user", "content": prompt}],
                    "response_format": {"type": "json_object"},
                    "temperature": 0.1
                },
                timeout=15.0
            )
            
            if res_ia.status_code == 429:
                logger.warning(f"Limite de Tokens atingido (429). A aguardar 6s (Tentativa {tentativas+1}/{max_tentativas})...")
                await asyncio.sleep(6.0) 
                tentativas += 1
                continue
            
            if res_ia.status_code != 200:
                logger.error(f"Groq bloqueou o pedido: {res_ia.status_code} - {res_ia.text}")
                return
            
            dados_json_string = res_ia.json()["choices"][0]["message"]["content"]
            dados_json = json.loads(dados_json_string)
            
            url_destino = f"{settings.laravel_url}/api/messages/{message_id}/metadata"
            
            # 🔥 CORREÇÃO 1: Usa o client reutilizável para o POST interno também
            res_laravel = await client.post(
                url_destino,
                json=dados_json,
                timeout=10.0
            )
            
            if res_laravel.status_code == 200:
                logger.info(f"✅ Metadados guardados no Laravel para a msg {message_id}: {dados_json}")
            else:
                logger.error(f"❌ Laravel rejeitou os metadados (Msg {message_id}): HTTP {res_laravel.status_code}")
            return 
            
        except Exception as e:
            logger.error(f"❌ Erro na análise de background: {e}")
            break