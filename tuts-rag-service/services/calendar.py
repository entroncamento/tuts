import re
import datetime
import logging
import asyncio
import os
from google.oauth2 import service_account
from googleapiclient.discovery import build

from config import settings

logger = logging.getLogger("tuts")

SCOPES = ["https://www.googleapis.com/auth/calendar.events"]

# Limites de Segurança (Prevenção de DoS e Spam)
MAX_EVENTOS = 10
MAX_DIAS_OFFSET = 365
MAX_TEMA_LENGTH = 100


def _obter_servico_calendario():
    if not settings.google_calendar_target_id:
        logger.warning("[CALENDAR] Inativo: google_calendar_target_id não configurado.")
        return None

    if not settings.service_account_file or not settings.service_account_file.strip():
        logger.warning("[CALENDAR] Inativo: caminho da service account não configurado.")
        return None

    if not os.path.exists(settings.service_account_file):
        logger.error("[CALENDAR] Erro Crítico: Ficheiro de service account não encontrado. Garante que está injetado pelo Secret Manager.")
        return None

    try:
        creds = service_account.Credentials.from_service_account_file(
            settings.service_account_file,
            scopes=SCOPES,
        )
        return build("calendar", "v3", credentials=creds, cache_discovery=False)
    except Exception as exc:
        logger.error("[CALENDAR] Erro no Google Calendar Auth: %s", type(exc).__name__)
        return None


def _criar_eventos_sync(eventos: list):
    """
    Função síncrona que efetivamente comunica com a API do Google.
    AGORA DEVE SER CHAMADA APENAS APÓS CONFIRMAÇÃO DO UTILIZADOR.
    """
    service = _obter_servico_calendario()
    if not service:
        return

    eventos_criados = 0
    for evento in eventos[:MAX_EVENTOS]: # Dupla barreira de segurança
        try:
            service.events().insert(
                calendarId=settings.google_calendar_target_id,
                body=evento,
            ).execute()
            eventos_criados += 1
        except Exception as exc:
            # Não expomos o summary do evento no erro para evitar fugas de PII
            logger.error("[CALENDAR] Erro ao inserir evento na API do Google: %s", type(exc).__name__)

    logger.info("[CALENDAR] Sucesso: %d eventos agendados no Google Calendar.", eventos_criados)


async def extrair_propostas_calendario(resposta_texto: str, uc_nome: str) -> dict | None:
    """
    Extrai as intenções de calendário da resposta da IA.
    NÃO cria os eventos automaticamente. Devolve uma estrutura para o Frontend pedir confirmação.
    """
    bloco_cal = re.search(r"\[CALENDARIO\](.*?)\[/CALENDARIO\]", resposta_texto, re.DOTALL)
    if not bloco_cal:
        return None

    linhas_plano = bloco_cal.group(1).strip().split("\n")
    eventos_propostos = []
    
    # Usar hora local/absoluta sem misturar UTC no base time para evitar conflitos de Timezone
    agora = datetime.datetime.now()

    for linha in linhas_plano:
        # Se a IA tentar fazer spam, cortamos logo aos 10 eventos
        if len(eventos_propostos) >= MAX_EVENTOS:
            logger.warning("[CALENDAR] Limite de %d eventos atingido. Linhas extra ignoradas.", MAX_EVENTOS)
            break

        partes = linha.split("|")
        if len(partes) < 2:
            continue

        try:
            dia_offset = int(partes[0].strip())
            
            # Sanitização do offset (Impede agendamentos no passado ou daqui a 10 anos)
            if dia_offset < 0 or dia_offset > MAX_DIAS_OFFSET:
                continue

            # Sanitização do texto (Impede payloads gigantes ou injeções SQL/NoSQL no DB local)
            tema_bruto = partes[1].strip()
            tema_seguro = tema_bruto[:MAX_TEMA_LENGTH].replace("<", "").replace(">", "")

            # Fix do Timezone: O Google espera o tempo local (sem tzinfo) se indicarmos o timeZone na request
            start_time = (agora + datetime.timedelta(days=dia_offset)).replace(
                hour=10,
                minute=0,
                second=0,
                microsecond=0,
            )
            end_time = start_time + datetime.timedelta(hours=2)

            evento_dict = {
                "summary": f"Estudo {uc_nome}: {tema_seguro}",
                "description": f"Plano de estudo gerado pelo TUT'S para a cadeira de {uc_nome}.",
                "start": {"dateTime": start_time.isoformat(), "timeZone": "Europe/Lisbon"},
                "end": {"dateTime": end_time.isoformat(), "timeZone": "Europe/Lisbon"},
            }
            eventos_propostos.append(evento_dict)
            
        except ValueError:
            continue

    if not eventos_propostos:
        return None

    return {
        "eventos_propostos": eventos_propostos,
        "requer_confirmacao": True,
        "total": len(eventos_propostos)
    }


async def confirmar_e_criar_eventos(eventos_validados: list):
    """
    Novo endpoint lógico. O FastAPI deve chamar isto APENAS quando o frontend
    enviar um POST a confirmar que o utilizador quer agendar estes eventos.
    """
    if not eventos_validados:
        return
        
    loop = asyncio.get_running_loop()
    # Executa a chamada à API do Google no background sem bloquear o event loop
    await loop.run_in_executor(None, _criar_eventos_sync, eventos_validados)