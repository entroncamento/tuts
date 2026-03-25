import os
from langchain_community.document_loaders import PyMuPDFLoader
from langchain_text_splitters import RecursiveCharacterTextSplitter
from langchain_community.vectorstores import FAISS

from config import settings, FAISS_INDEX_FILE
from core.ml_models import embeddings_model

def build_index(temp_path: str, filename: str, uc: str, chunk_size: int, chunk_overlap: int) -> tuple[int, list]:
    loader = PyMuPDFLoader(temp_path)
    documentos = loader.load()

    # O filename já vem limpo do professores.py, mas garantimos aqui de qualquer forma.
    clean_filename = os.path.basename(filename)

    for doc in documentos:
        pagina_humana = doc.metadata.get("page", 0) + 1
        # Injeta o cabeçalho no RAG para a citação ser perfeita!
        doc.page_content = f"[CABEÇALHO FONTE: {clean_filename}:{pagina_humana}]\n{doc.page_content}"

    text_splitter = RecursiveCharacterTextSplitter(chunk_size=chunk_size, chunk_overlap=chunk_overlap, separators=["\n\n", "\n", ". ", " ", ""])
    chunks = text_splitter.split_documents(documentos)

    db_path = os.path.join(settings.base_faiss_dir, uc)
    os.makedirs(db_path, exist_ok=True)
    index_path = os.path.join(db_path, FAISS_INDEX_FILE)

    if os.path.exists(index_path):
        vs = FAISS.load_local(db_path, embeddings_model, allow_dangerous_deserialization=True)
        vs.add_documents(chunks)
    else:
        vs = FAISS.from_documents(chunks, embeddings_model)

    vs.save_local(db_path)
    return len(chunks), chunks