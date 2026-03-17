import datetime
import os.path
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials
from google_auth_oauthlib.flow import InstalledAppFlow
from googleapiclient.discovery import build

# Se mudarmos estes scopes, temos de apagar o token.json e gerar um novo
SCOPES = ['https://www.googleapis.com/auth/calendar.events']

def obter_servico_calendario():
    """Mostra o login do Google e devolve o serviço para criar eventos."""
    creds = None
    # O ficheiro token.json guarda o acesso do utilizador depois do primeiro login
    if os.path.exists('token.json'):
        creds = Credentials.from_authorized_user_file('token.json', SCOPES)
        
    # Se não houver credenciais válidas, obriga o utilizador a fazer login
    if not creds or not creds.valid:
        if creds and creds.expired and creds.refresh_token:
            creds.refresh(Request())
        else:
            flow = InstalledAppFlow.from_client_secrets_file('credentials.json', SCOPES)
            creds = flow.run_local_server(port=0)
            
        # Guarda as credenciais para a próxima vez
        with open('token.json', 'w') as token:
            token.write(creds.to_json())

    try:
        service = build('calendar', 'v3', credentials=creds)
        return service
    except Exception as e:
        print(f"Erro ao ligar à Google: {e}")
        return None

def criar_evento_teste():
    """Cria um evento de teste no teu calendário para vermos se funciona."""
    service = obter_servico_calendario()
    if not service:
        return
    
    agora = datetime.datetime.utcnow()
    daqui_a_uma_hora = agora + datetime.timedelta(hours=1)

    evento = {
      'summary': 'Estudar Teorias da Comunicação 📚',
      'location': 'Universidade de Aveiro',
      'description': 'Plano de estudo gerado pelo TUTs.',
      'start': {
        'dateTime': agora.isoformat() + 'Z',
        'timeZone': 'Europe/Lisbon',
      },
      'end': {
        'dateTime': daqui_a_uma_hora.isoformat() + 'Z',
        'timeZone': 'Europe/Lisbon',
      },
    }

    evento_criado = service.events().insert(calendarId='primary', body=evento).execute()
    print(f"✅ Sucesso! Evento criado: {evento_criado.get('htmlLink')}")

if __name__ == '__main__':
    print("A iniciar o teste de ligação ao Google Calendar...")
    criar_evento_teste()