import re
import asyncio
import datetime

from config import logger

try:
    from calendar_service import obter_servico_calendario
except ImportError:
    obter_servico_calendario = None

def _criar_evento_google_sync(service, evento: dict) -> None:
    try:
        service.events().insert(calendarId="primary", body=evento).execute()
    except Exception as exc:
        logger.error("Erro ao inserir no calendário: %s", exc)

async def processar_calendario(resposta_limpa: str, uc_nome: str, executor, loop) -> str:
    try:
        bloco_cal = re.search(r"\[CALENDARIO\](.*?)\[/CALENDARIO\]", resposta_limpa, re.DOTALL)
        if not bloco_cal:
            return resposta_limpa

        linhas_plano = bloco_cal.group(1).strip().split("\n")

        if obter_servico_calendario:
            service = await loop.run_in_executor(executor, obter_servico_calendario)
            if service:
                agora = datetime.datetime.now(datetime.timezone.utc)
                for linha in linhas_plano:
                    partes = linha.split("|")
                    if len(partes) < 2:
                        continue
                    try:
                        dia_offset = int(partes[0].strip())
                        tema = partes[1].strip()
                        data_evento = agora + datetime.timedelta(days=dia_offset)
                        start_time = data_evento.replace(hour=10, minute=0, second=0, microsecond=0)
                        end_time = start_time + datetime.timedelta(hours=2)
                        
                        evento_dict = {
                            "summary":     f"Estudo {uc_nome}: {tema}",
                            "description": f"Plano de estudo gerado pelo TUT'S.",
                            "start": {"dateTime": start_time.isoformat(), "timeZone": "Europe/Lisbon"},
                            "end":   {"dateTime": end_time.isoformat(),   "timeZone": "Europe/Lisbon"},
                        }
                        
                        # 🚀 AQUI ESTÁ A MAGIA!
                        # Await garante que o Google Calendar recebe o evento antes do loop seguir em frente.
                        await loop.run_in_executor(executor, _criar_evento_google_sync, service, evento_dict)
                        
                    except ValueError:
                        continue

        resposta_limpa = re.sub(r"\[CALENDARIO\].*?\[/CALENDARIO\]", "", resposta_limpa, flags=re.DOTALL).strip()
        resposta_limpa += "\n\n**Os blocos de estudo foram agendados!**"
    except Exception as exc:
        logger.error("Erro no Calendário: %s", exc)
    
    return resposta_limpa