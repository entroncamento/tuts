from fastapi import FastAPI, UploadFile, File, Form, HTTPException
from pydantic import BaseModel
import os
import shutil
import json
import uuid
import httpx
from dotenv import load_dotenv

import easyocr

from langchain_community.vectorstores import FAISS
from langchain_huggingface import HuggingFaceEmbeddings
from langchain_community.document_loaders import PyMuPDFLoader
from langchain_text_splitters import RecursiveCharacterTextSplitter
from langchain_community.retrievers import BM25Retriever # 🌟 NÍVEL 3: Motor Keyword
from sentence_transformers import CrossEncoder # 🌟 NÍVEL 2: Importar o CrossEncoder para fazer o Re-Ranking

load_dotenv()

app = FastAPI()

embeddings = HuggingFaceEmbeddings(model_name="paraphrase-multilingual-MiniLM-L12-v2")
FAISS_DB_DIR = "faiss_db"

print("A carregar o motor de OCR...")
leitor_ocr = easyocr.Reader(['pt'])
print("Motor de OCR pronto!")

# 🌟 NÍVEL 2: Carregar o modelo de Re-Ranking (Multilingue para suportar PT)
print("A carregar o modelo de Re-Ranking...")
reranker = CrossEncoder('cross-encoder/mmarco-mMiniLMv2-L12-H384-v1')
print("Re-Ranker pronto!")
print("Sistemas Prontos!")

# ==========================================
# FUNÇÃO AUXILIAR: Falar com a IAedu
# ==========================================
async def chamar_iaedu(prompt: str) -> str:
    api_key = os.getenv("IAEDU_API_KEY")
    agent_id = os.getenv("IAEDU_AGENT_ID")      
    channel_id = os.getenv("IAEDU_CHANNEL_ID")  
    thread_id = os.getenv("IAEDU_THREAD_ID")
    
    if not all([api_key, agent_id, channel_id, thread_id]):
        raise Exception("Credenciais IAedu incompletas.")

    url_iaedu = f"https://api.iaedu.pt/agent-chat/api/v1/agent/{agent_id}/stream"
    headers = {"x-api-key": api_key}
    form_data = {"channel_id": channel_id, "thread_id": thread_id, "user_info": "{}", "message": prompt}
    
    resposta_limpa = ""
    async with httpx.AsyncClient() as client:
        resposta_api = await client.post(url_iaedu, headers=headers, data=form_data, timeout=60.0)
        resposta_api.raise_for_status()
        linhas = resposta_api.text.split("\n\n")
        for linha in linhas:
            if linha.strip():
                try:
                    dados = json.loads(linha)
                    if dados.get("type") == "message" and "content" in dados and "content" in dados["content"]:
                        resposta_limpa = dados["content"]["content"]
                        break
                except json.JSONDecodeError:
                    continue
    return resposta_limpa


# ==========================================
# ROTA 1: INGESTÃO 
# ==========================================
@app.post("/ingestao")
async def ingestao(file: UploadFile = File(...)):
    if not file.filename.lower().endswith(".pdf"):
        raise HTTPException(status_code=400, detail="Apenas ficheiros PDF são suportados.")

    temp_path = f"temp_{uuid.uuid4().hex}_{file.filename}"
    
    try:
        with open(temp_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)
        
        loader = PyMuPDFLoader(temp_path)
        documentos = loader.load()
        
        for doc in documentos:
            pagina_humana = doc.metadata.get("page", 0) + 1
            doc.page_content = f"[FONTE: {file.filename} | PÁGINA: {pagina_humana}]\n{doc.page_content}"

        text_splitter = RecursiveCharacterTextSplitter(
            chunk_size=1200, 
            chunk_overlap=250,
            separators=["\n\n", "\n", ". ", " ", ""]
        )
        chunks = text_splitter.split_documents(documentos)
        
        if not chunks:
            raise HTTPException(status_code=422, detail="O PDF não contém texto extraível.")
        
        if os.path.exists(os.path.join(FAISS_DB_DIR, "index.faiss")):
            vector_store = FAISS.load_local(FAISS_DB_DIR, embeddings, allow_dangerous_deserialization=True)
            vector_store.add_documents(chunks)
        else:
            vector_store = FAISS.from_documents(chunks, embeddings)
            
        vector_store.save_local(FAISS_DB_DIR)
        
        return {
            "mensagem": "PDF processado e indexado com sucesso na Base de Dados FAISS!",
            "ficheiro": file.filename,
            "total_paginas": len(documentos),
            "total_chunks_gerados": len(chunks)
        }
        
    finally:
        if os.path.exists(temp_path):
            os.remove(temp_path)

# ==========================================
# ROTA 2: PERGUNTAR (NÍVEL 3 - O BOSS FINAL)
# ==========================================
@app.post("/perguntar")
async def perguntar(
    texto: str = Form(...),
    uc: str = Form("Geral"),
    preferencia: str = Form("textual"),
    historico: str = Form("[]"), # 🌟 NÍVEL 3: Receber histórico do Laravel (JSON String)
    imagem: UploadFile = File(None)
):
    if not os.path.exists(os.path.join(FAISS_DB_DIR, "index.faiss")):
        return {"status": "erro", "mensagem": "A base de dados FAISS não existe. Faz a ingestão de um PDF primeiro!"}

    texto_final_aluno = texto
    
    # 👁️ OCR
    if imagem:
        try:
            conteudo_img = await imagem.read()
            resultados_texto = leitor_ocr.readtext(conteudo_img, detail=0)
            texto_extraido = "\n".join(resultados_texto)
            
            if texto_extraido.strip():
                texto_final_aluno += f"\n\n[TEXTO LIDO DA FOTOGRAFIA ENVIADA PELO ALUNO]:\n{texto_extraido}"
        except Exception as e:
            print(f"Erro no OCR: {e}")

    # 🌟 NÍVEL 3: Reescrita Contextual (Expansão de Query + Memória)
    try:
        mensagens_historico = json.loads(historico)
    except:
        mensagens_historico = []

    pergunta_pesquisa = texto_final_aluno
    if mensagens_historico:
        # Pede à IA para ler o histórico e reescrever a pergunta de forma clara para um motor de busca
        prompt_reescrita = f"""Dado o seguinte histórico de conversa e a última pergunta do aluno, reescreve a última pergunta para ser uma query de pesquisa independente e rica em palavras-chave. Se a pergunta não precisar do histórico, mantém-na igual. NÃO respondas à pergunta, apenas devolve a query reformulada.
Histórico: {json.dumps(mensagens_historico[-3:], ensure_ascii=False)}
Última pergunta: {texto_final_aluno}
Query Reformulada:"""
        try:
            print("A reescrever a pergunta para expansão...")
            pergunta_pesquisa = await chamar_iaedu(prompt_reescrita)
            print(f"Super-Pergunta gerada: {pergunta_pesquisa}")
        except Exception as e:
            print("Erro na reescrita da query, a usar a original.")

    # 🧠 RAG HÍBRIDO (FAISS + BM25)
    vector_store = FAISS.load_local(FAISS_DB_DIR, embeddings, allow_dangerous_deserialization=True)
    
    # Pesquisa 1: Semântica (FAISS)
    docs_faiss = vector_store.similarity_search(pergunta_pesquisa, k=15)
    
    # Pesquisa 2: Palavras-chave Exatas (BM25)
    todos_documentos = list(vector_store.docstore._dict.values())
    bm25_retriever = BM25Retriever.from_documents(todos_documentos)
    bm25_retriever.k = 10
    docs_bm25 = bm25_retriever.invoke(pergunta_pesquisa)
    
    # Juntar os dois mundos e remover repetidos
    documentos_unicos = {}
    for doc in docs_faiss + docs_bm25:
        documentos_unicos[doc.page_content] = doc
    docs_hibridos = list(documentos_unicos.values())
    
    # 🌟 RE-RANKING (Cross-Encoder com os resultados do FAISS + BM25)
    pares_para_avaliar = [[pergunta_pesquisa, doc.page_content] for doc in docs_hibridos]
    notas = reranker.predict(pares_para_avaliar)
    docs_com_notas = list(zip(docs_hibridos, notas))
    docs_ordenados = sorted(docs_com_notas, key=lambda x: x[1], reverse=True)
    
    # Selecionar os 3 finalistas absolutos
    docs_finais = [doc for doc, nota in docs_ordenados[:3]]
    contexto_recuperado = "\n\n---\n\n".join([doc.page_content for doc in docs_finais])
    
    # 🎨 Preferência e Prompt Final
    regra_formato = ""
    if preferencia == "visual":
        regra_formato = "\nATENÇÃO: O aluno selecionou a preferência VISUAL. Deves tentar resumir a tua resposta usando código Mermaid.js (gráficos ou fluxogramas) sempre que a matéria permitir, envolvendo o código em blocos ```mermaid."

    prompt_rag = f"""És o STU, o assistente académico da Universidade de Aveiro.
Estás a ajudar um aluno no contexto específico da Unidade Curricular: {uc}.{regra_formato}

A tua missão é ajudar o aluno respondendo às suas perguntas.
Usa APENAS a informação fornecida no Contexto abaixo. Sempre que usares informação, tenta referir a página de onde a tiraste.
Se a resposta não estiver no Contexto, diz EXATAMENTE: "Desculpa, mas não encontrei informação sobre isso nos documentos validados de {uc}."

Contexto retirado dos PDFs:
{contexto_recuperado}

Pergunta original do aluno: {texto_final_aluno}

Resposta:"""

    try:
        resposta_limpa = await chamar_iaedu(prompt_rag)
    except Exception as e:
        resposta_limpa = f"Erro de comunicação: {str(e)}"

    # Extrair as fontes COM o número da página exata e nome limpo! (Atenção: ler dos docs_finais)
    fontes_com_pagina = []
    for doc in docs_finais:
        nome_sujo = os.path.basename(doc.metadata.get("source", "PDF Desconhecido"))
        nome_limpo = nome_sujo[38:] if nome_sujo.startswith("temp_") else nome_sujo
        pagina = doc.metadata.get("page", 0) + 1 
        fontes_com_pagina.append(f"{nome_limpo} (Página {pagina})")
        
    fontes = list(set(fontes_com_pagina))
    
    return {
        "status": "sucesso",
        "pergunta_original": texto_final_aluno,
        "query_expandida": pergunta_pesquisa, # 👈 O Laravel vai conseguir ver como a IA transformou a pergunta!
        "resposta_stu": resposta_limpa,
        "fontes_consultadas": fontes
    }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8001)