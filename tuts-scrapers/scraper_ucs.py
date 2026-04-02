from bs4 import BeautifulSoup
import json

print("🚜 A ligar o trator local... A ler o HTML de MTC...")

caminho_html = 'dados_extraidos/html_mtc.html'

# Tentar abrir o HTML que gravaste
try:
    with open(caminho_html, 'r', encoding='utf-8') as f:
        html = f.read()
except FileNotFoundError:
    print(f"❌ Não encontrei o ficheiro {caminho_html}! Cria-o e cola lá o HTML.")
    exit()

sopa = BeautifulSoup(html, 'html.parser')

print("✅ HTML carregado! A procurar as cadeiras de MTC...")

lista_cadeiras = []
cadeiras_vistas = set()

# Procurar todos os links <a> na página
links = sopa.find_all('a', href=True)

for link in links:
    href = link['href']
    nome_cadeira = link.text.strip()
    
    # A magia: Se o link tiver "/pt/uc/" E tiver um nome
    if '/pt/uc/' in href and nome_cadeira and len(nome_cadeira) > 2:
        # Limpar espaços duplos ou quebras de linha que o HTML tenha
        nome_cadeira = " ".join(nome_cadeira.split())
        
        if nome_cadeira not in cadeiras_vistas:
            lista_cadeiras.append({
                "nome_uc": nome_cadeira,
                "url_uc": f"https://www.ua.pt{href}"
            })
            cadeiras_vistas.add(nome_cadeira)
            print(f"📚 Encontrada: {nome_cadeira}")

# Guardar tudo num JSON lindíssimo para usares no Laravel!
caminho_json = 'dados_extraidos/cadeiras_mtc.json'
with open(caminho_json, 'w', encoding='utf-8') as f:
    json.dump(lista_cadeiras, f, ensure_ascii=False, indent=4)

print(f"\n🚀 Sucesso absoluto! Extraídas {len(lista_cadeiras)} UCs do teu curso.")
print(f"📁 Ficheiro guardado em: {caminho_json}")