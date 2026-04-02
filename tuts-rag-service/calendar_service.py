import datetime
import logging
import os

from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials
from google_auth_oauthlib.flow import InstalledAppFlow
from googleapiclient.discovery import build

logger = logging.getLogger("tuts")

# ─────────────────────────────────────────────────────────────────────────────
# Constantes
# ─────────────────────────────────────────────────────────────────────────────
SCOPES          = ["https://www.googleapis.com/auth/calendar"]
TOKEN_FILE       = "token.json"        # SonarQube: literal duplicado → constante
CREDENTIALS_FILE = "credentials.json"


def obter_servico_calendario():
    """
    Autentica com OAuth2 e devolve um serviço Google Calendar pronto a usar.
    Devolve None se a autenticação falhar.
    """
    creds = None

    if os.path.exists(TOKEN_FILE):
        try:
            creds = Credentials.from_authorized_user_file(TOKEN_FILE, SCOPES)
        except Exception as exc:
            logger.warning("Erro ao carregar token: %s", exc)

    if not creds or not creds.valid:
        if creds and creds.expired and creds.refresh_token:
            try:
                creds.refresh(Request())
            except Exception as exc:
                logger.error("Erro ao refrescar token OAuth2: %s", exc)
                return None
        else:
            if not os.path.exists(CREDENTIALS_FILE):
                logger.error("Ficheiro '%s' não encontrado. Autenticação impossível.", CREDENTIALS_FILE)
                return None
            try:
                flow  = InstalledAppFlow.from_client_secrets_file(CREDENTIALS_FILE, SCOPES)
                creds = flow.run_local_server(port=0)
            except Exception as exc:
                logger.error("Erro no fluxo OAuth2: %s", exc)
                return None

        try:
            with open(TOKEN_FILE, "w", encoding="utf-8") as token:
                token.write(creds.to_json())
        except Exception as exc:
            logger.warning("Não foi possível guardar o token em '%s': %s", TOKEN_FILE, exc)

    try:
        return build("calendar", "v3", credentials=creds)
    except Exception as exc:
        logger.error("Erro ao construir serviço Google Calendar: %s", exc)
        return None


def criar_evento(service, summary: str, dia_offset: int, uc_nome: str) -> None:
    """Cria um evento de estudo no Google Calendar para daqui a `dia_offset` dias."""
    # SonarQube: timezone-aware em vez de utcnow() deprecated
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


# Este bloco TEM de estar encostado à esquerda (0 espaços de indentação)
if __name__ == "__main__":
    print("A iniciar o fluxo de autenticação com a Google...")
    servico = obter_servico_calendario()
    if servico:
        print("✅ SUCESSO! O teu novo token.json foi gerado com os poderes todos!")
    else:
        print("❌ Ops, algo correu mal.")