import datetime
import logging
import os

from google.oauth2 import service_account
from googleapiclient.discovery import build

logger = logging.getLogger("tuts")

# ─────────────────────────────────────────────────────────────────────────────
# Constantes
# ─────────────────────────────────────────────────────────────────────────────
SCOPES = ["https://www.googleapis.com/auth/calendar"]
SERVICE_ACCOUNT_FILE = "service_account.json" # 🔥 O ficheiro da conta bot!

def obter_servico_calendario():
    """
    Autentica com OAuth2 usando uma Service Account (perfeito para Docker/Backend).
    """
    if not os.path.exists(SERVICE_ACCOUNT_FILE):
        logger.error("Ficheiro '%s' não encontrado. Autenticação impossível.", SERVICE_ACCOUNT_FILE)
        return None

    try:
        creds = service_account.Credentials.from_service_account_file(
            SERVICE_ACCOUNT_FILE, scopes=SCOPES
        )
        return build("calendar", "v3", credentials=creds)
    except Exception as exc:
        logger.error("Erro ao construir serviço Google Calendar via Service Account: %s", exc)
        return None

def criar_evento(service, summary: str, dia_offset: int, uc_nome: str) -> None:
    """Cria um evento de estudo no Google Calendar para daqui a `dia_offset` dias."""
    agora      = datetime.datetime.now(datetime.timezone.utc)
    start_time = (agora + datetime.timedelta(days=dia_offset)).replace(
        hour=10, minute=0, second=0, microsecond=0
    )
    end_time = start_time + datetime.timedelta(hours=2)

    evento = {
        "summary":     f"📚 Estudo {uc_nome}: {summary}",
        "description": f"Plano de estudo gerado pelo TUTs para a UC de {uc_nome}. Bom estudo!",
        "start": {"dateTime": start_time.isoformat(), "timeZone": "Europe/Lisbon"},
        "end":   {"dateTime": end_time.isoformat(),   "timeZone": "Europe/Lisbon"},
    }

    try:
        service.events().insert(calendarId="primary", body=evento).execute()
        logger.info("Evento criado: %s (dia +%d)", summary, dia_offset)
    except Exception as exc:
        logger.error("Erro ao criar evento no calendário: %s", exc)