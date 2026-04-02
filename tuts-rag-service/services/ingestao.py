import os
import fitz
from langchain_community.document_loaders import PyMuPDFLoader
from langchain_text_splitters import RecursiveCharacterTextSplitter
from langchain_community.vectorstores import FAISS

from config import settings, FAISS_INDEX_FILE, logger
from core.ml_models import embeddings_model, leitor_ocr


def build_index(temp_path: str, filename: str, uc: str, chunk_size: int, chunk_overlap: int) -> tuple[int, list]:
    # 1. Carregar o texto base nativo do PDF
    loader = PyMuPDFLoader(temp_path)
    documentos = loader.load()
    clean_filename = os.path.basename(filename)

    # 2. Abrir o PDF para caçar imagens e fazer OCR
    try:
        with fitz.open(temp_path) as pdf_document:
            for page_num in range(len(pdf_document)):
                pagina_fitz = pdf_document[page_num]
                lista_imagens = pagina_fitz.get_images(full=True)

                texto_ocr_pagina = []
                for img in lista_imagens:
                    xref = img[0]
                    base_image = pdf_document.extract_image(xref)

                    # Ignorar imagens minúsculas (logos, ícones, etc.)
                    if base_image["width"] >= 100 and base_image["height"] >= 100:
                        try:
                            resultados_ocr = leitor_ocr.readtext(base_image["image"], detail=0)
                            texto_extraido = "\n".join(resultados_ocr)
                            if texto_extraido.strip():
                                texto_ocr_pagina.append(texto_extraido.strip())
                        except Exception as exc:
                            logger.warning("Erro OCR numa imagem do PDF %s: %s", clean_filename, exc)

                # Juntar texto OCR ao conteúdo da página, com verificação de índice
                if texto_ocr_pagina and page_num < len(documentos):
                    texto_junto = "\n\n[TEXTO EXTRAÍDO DE IMAGENS NESTA PÁGINA]:\n" + "\n---\n".join(texto_ocr_pagina)
                    documentos[page_num].page_content += texto_junto

    except Exception as exc:
        logger.error("Falha ao fazer OCR no PDF %s, a prosseguir com texto nativo. Erro: %s", clean_filename, exc)

    # 3. Injectar cabeçalho para citação exacta
    for doc in documentos:
        pagina_humana = doc.metadata.get("page", 0) + 1
        doc.page_content = f"[CABEÇALHO FONTE: {clean_filename}:{pagina_humana}]\n{doc.page_content}"

    # 4. Chunking
    text_splitter = RecursiveCharacterTextSplitter(
        chunk_size=chunk_size,
        chunk_overlap=chunk_overlap,
        separators=["\n\n", "\n", ". ", " ", ""]
    )
    chunks = text_splitter.split_documents(documentos)

    # 5. Guardar no FAISS
    # ⚠️  Race condition potencial se dois uploads chegarem em simultâneo para a mesma UC.
    # Aplicar lock no router antes de chamar build_index no executor.
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