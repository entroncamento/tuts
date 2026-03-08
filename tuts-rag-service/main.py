from fastapi import FastAPI
from pydantic import BaseModel
from langchain_community.embeddings import HuggingFaceEmbeddings
import faiss
from langchain_community.vectorstores import FAISS
from langchain_huggingface import HuggingFaceEmbeddings

app = FastAPI()

# Modelo para receber a pergunta do Laravel
class Pergunta(BaseModel):
    texto: str

# 1. Carregar o cérebro (FAISS) que criámos nos scrapers
# Ajusta o caminho se a tua pasta 'faiss_index' estiver noutro sítio
try:
    embeddings = HuggingFaceEmbeddings(model_name="all-MiniLM-L6-v2")
    vector_store = FAISS.load_local("faiss_index", embeddings, allow_dangerous_deserialization=True)
    print("✅ Índice FAISS carregado com sucesso!")
except Exception as e:
    print(f"❌ Erro ao carregar FAISS: {e}")

@app.post("/perguntar")
async def perguntar(pergunta: Pergunta):
    # 2. Procurar no "conhecimento" da UA/Merton
    docs = vector_store.similarity_search(pergunta.texto, k=3)
    
    # Juntar o conteúdo encontrado
    contexto = "\n".join([doc.page_content for doc in docs])
    
    return {
        "status": "sucesso",
        "pergunta": pergunta.texto,
        "conhecimento_recuperado": contexto
    }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8001)