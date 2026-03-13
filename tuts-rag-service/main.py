from fastapi import FastAPI, UploadFile, File
from pydantic import BaseModel
import requests
import os
import shutil
import json
from langchain_community.vectorstores import FAISS
from langchain_huggingface import HuggingFaceEmbeddings
from langchain_community.document_loaders import PyMuPDFLoader
from langchain_text_splitters import RecursiveCharacterTextSplitter

app = FastAPI()

class Pergunta(BaseModel):
    texto: str

# Carregamos o "Cérebro Poliglota" uma única vez para ser usado em ambas as rotas
embeddings = HuggingFaceEmbeddings(model_name="paraphrase-multilingual-MiniLM-L12-v2")

# ==========================================
# ROTA 1: INGESTÃO (Receber e processar PDFs)
# ==========================================
@app.post("/ingestao")
async def ingestao(file: UploadFile = File(...)):
    # 1. Guardar o PDF temporariamente para o Python o conseguir ler
    temp_path = f"temp_{file.filename}"
    with open(temp_path, "wb") as buffer:
        shutil.copyfileobj(file.file, buffer)
    
    # 2. Ler o PDF (respeitando colunas) e partir em chunks
    loader = PyMuPDFLoader(temp_path)
    documentos = loader.load()
    text_splitter = RecursiveCharacterTextSplitter(chunk_size=1000, chunk_overlap=200)
    chunks = text_splitter.split_documents(documentos)
    
    # 3. Adicionar à Base de Dados FAISS
    try:
        # Se a base de dados já existir, adicionamos os novos PDFs a ela
        vector_store = FAISS.load_local("faiss_db", embeddings, allow_dangerous_deserialization=True)
        vector_store.add_documents(chunks)
    except Exception:
        # Se não existir (primeira vez), criamos uma nova
        vector_store = FAISS.from_documents(chunks, embeddings)
        
    vector_store.save_local("faiss_db")
    
    # 4. Limpar o PDF temporário (para não encher o disco)
    os.remove(temp_path)
    
    return {
        "mensagem": "PDF processado e indexado com sucesso na Base de Dados FAISS!",
        "ficheiro": file.filename,
        "total_paginas": len(documentos),
        "total_chunks_gerados": len(chunks)
    }

# ==========================================
# ROTA 2: PERGUNTAR (A IA responde)
# ==========================================
@app.post("/perguntar")
async def perguntar(pergunta: Pergunta):
    try:
        vector_store = FAISS.load_local("faiss_db", embeddings, allow_dangerous_deserialization=True)
    except Exception:
        return {"status": "erro", "mensagem": "A base de dados FAISS não existe. Faz a ingestão de um PDF primeiro!"}

    # Passo A: O FAISS procura os blocos de PDF relevantes
    docs = vector_store.similarity_search(pergunta.texto, k=3)
    contexto_recuperado = "\n\n---\n\n".join([doc.page_content for doc in docs])
    
    # Passo B: O PROMPT ESTUDADO
    prompt_rag = f"""És o STU, o assistente académico da Universidade de Aveiro.
A tua missão é ajudar os alunos respondendo às suas perguntas, mas tens uma regra de ouro:
Usa APENAS a informação fornecida no Contexto abaixo. 
Se a resposta não estiver no Contexto, diz EXATAMENTE: "Desculpa, mas não encontrei informação sobre isso nos documentos validados da disciplina."

Contexto retirado dos PDFs:
{contexto_recuperado}

Pergunta do aluno: {pergunta.texto}

Resposta:"""

    # Passo C: Ligar à API do IAedu
    url_iaedu = "https://api.iaedu.pt/agent-chat//api/v1/agent/cmamvd3n40000c801qeacoad2/stream"
    headers = {"x-api-key": "sk-usr-bbcdniwdpgaf5xodrkpuh5hdfeqvz7ceiig"}
    form_data = {
        "channel_id": "cmmnf00yv2b6vhv01plgemi1t",
        "thread_id": "u4UpAHIXOO1dLFuPZdrac",
        "user_info": "{}",
        "message": prompt_rag 
    }
    
    resposta_api = requests.post(url_iaedu, headers=headers, data=form_data)
    
    # Passo D: Filtrar o Stream da resposta do IAedu
    resposta_limpa = ""
    linhas = resposta_api.text.split("\n\n")
    for linha in linhas:
        if linha.strip():
            try:
                dados = json.loads(linha)
                if dados.get("type") == "message":
                    resposta_limpa = dados["content"]["content"]
                    break
            except Exception:
                continue
                
    if not resposta_limpa:
        resposta_limpa = "Erro: Não consegui processar a resposta do IAedu."

    # Passo E: Extrair as fontes
    fontes = list(set([doc.metadata.get("source", "PDF Desconhecido") for doc in docs]))
    
    return {
        "status": "sucesso",
        "pergunta": pergunta.texto,
        "resposta_stu": resposta_limpa,
        "fontes_consultadas": fontes
    }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8001)