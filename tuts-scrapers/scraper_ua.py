from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from bs4 import BeautifulSoup
import time
import json

url = 'https://www.ua.pt/pt/cursos/tipo/licenciaturas-mestrado-integrado'

print("🚜 A ligar o super-trator (Selenium)... a abrir um Chrome fantasma...")

# Configurar o Chrome para correr de forma invisível no fundo
opcoes = Options()
opcoes.add_argument("--headless=new") 
# Podes remover a linha de cima se quiseres ver o Chrome a abrir-se sozinho e a navegar como um fantasma!

# Iniciar o browser automático (O Selenium 4 faz o download do driver sozinho!)
browser = webdriver.Chrome(options=opcoes)
browser.get(url)

print("⏳ À espera que o JavaScript da UA carregue os cursos (3 segundos)...")
time.sleep(3) # Damos 3 segundos para a página da UA renderizar tudo

# Agora sim, sacamos o HTML completo e fechamos o browser
html = browser.page_source
browser.quit()

print("✅ Código HTML capturado! A extrair dados...")

# O resto da lógica genial que já tínhamos feito
sopa = BeautifulSoup(html, 'html.parser')
links = sopa.find_all('a', href=True)

lista_cursos = []
cursos_vistos = set()

for link in links:
    href = link['href']
    nome_curso = link.text.strip()
    
    # Se o link tiver "/pt/curso/" é porque encontrámos ouro!
    if '/pt/curso/' in href and nome_curso and len(nome_curso) > 5:
        # Limpar espaços a mais e quebras de linha
        nome_curso = " ".join(nome_curso.split())
        
        if nome_curso not in cursos_vistos:
            lista_cursos.append({
                "nome_curso": nome_curso,
                "url_curso": f"https://www.ua.pt{href}"
            })
            cursos_vistos.add(nome_curso)
            print(f"🎓 Encontrado: {nome_curso}")

caminho_ficheiro = 'dados_extraidos/cursos_ua.json'
with open(caminho_ficheiro, 'w', encoding='utf-8') as f:
    json.dump(lista_cursos, f, ensure_ascii=False, indent=4)

print(f"\n🚀 Sucesso! Foram extraídos {len(lista_cursos)} cursos.")
print(f"📁 Abre o ficheiro: {caminho_ficheiro}")