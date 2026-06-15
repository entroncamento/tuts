import os
import json
import uuid
import shutil
import fitz
import datetime
import time
from pathlib import Path
from langchain_community.document_loaders import PyMuPDFLoader
from langchain_text_splitters import RecursiveCharacterTextSplitter
from langchain_community.vectorstores import FAISS

from config import settings, FAISS_INDEX_FILE, MANIFEST_FILE, logger
from core.ml_models import embeddings_model, leitor_ocr
from core.utils import pasta_faiss_canonica_uc

# Limites de Segurança
MAX_PAGES_PER_PDF = 500
MAX_CHUNK_SIZE = 5000

def _validar_faiss_dir(db_path: str) -> None:
    """
    Defesa em profundidade contra Path Traversal e injeção de ficheiros.
    Garante que estamos a carregar FAISS exclusivamente do diretório autorizado.
    """
    base = Path(settings.base_faiss_dir).resolve()
    real = Path(db_path).resolve()

    if base != real and base not in real.parents:
        logger.error("[SEGURANÇA] Tentativa de aceder a FAISS fora do diretório base: %s", db_path)
        raise RuntimeError("Diretório FAISS fora da base permitida.")

def _validar_pdf(caminho_pdf: str) -> None:
    """
    Garante que o PDF não é uma bomba de processamento.
    """
    try:
        with fitz.open(caminho_pdf) as doc:
            if doc.page_count > MAX_PAGES_PER_PDF:
                raise ValueError(f"O PDF excede o limite máximo permitido de {MAX_PAGES_PER_PDF} páginas.")
            if doc.page_count == 0:
                raise ValueError("O PDF não contém páginas.")
    except Exception as e:
        raise ValueError(f"Ficheiro PDF malformado ou corrompido: {e}")

def _sincronizar_uc_para_hub(uc: str, db_path: Path) -> None:
    """
    Faz upload dos 3 ficheiros FAISS de uma UC para o HF Dataset repo.
    Opera em modo best-effort: falhas são registadas mas nunca propagadas.
    """
    hf_token = os.getenv("HF_TOKEN")
    hf_repo = os.getenv("HF_DATASET_REPO")

    if not hf_token or not hf_repo:
        logger.debug("[FAISS_SYNC] HF_TOKEN ou HF_DATASET_REPO não configurados — upload ignorado.")
        return

    try:
        from huggingface_hub import HfApi
        api = HfApi()

        uc_folder = db_path.name  # nome canónico, ex: "teorias_da_comunicacao"
        ficheiros = ["index.faiss", "index.pkl", "manifest.json"]
        uploaded = []

        for nome in ficheiros:
            caminho = db_path / nome
            if not caminho.exists():
                logger.warning("[FAISS_SYNC] Ficheiro não encontrado, upload ignorado: %s", caminho)
                continue

            api.upload_file(
                path_or_fileobj=str(caminho),
                path_in_repo=f"{uc_folder}/{nome}",
                repo_id=hf_repo,
                repo_type="dataset",
                token=hf_token,
                commit_message=f"sync: {uc_folder}/{nome}",
            )
            uploaded.append(nome)

        logger.info(
            "[FAISS_SYNC] UC '%s' sincronizada para %s (%d ficheiros: %s)",
            uc, hf_repo, len(uploaded), ", ".join(uploaded),
        )

    except Exception as exc:
        logger.warning(
            "[FAISS_SYNC] ⚠️  Falha ao sincronizar UC '%s' para HF Hub (%s). "
            "Os dados estão salvos localmente.",
            uc, type(exc).__name__,
        )

def build_index(
    temp_path: str,
    filename: str,
    uc: str,
    chunk_size: int,
    chunk_overlap: int,
    metadata_extra: dict | None = None,
) -> tuple[int, list]:
    t0_total = time.perf_counter()
    clean_filename = os.path.basename(filename)

    # 0) Validações de Segurança Iniciais
    if not 100 <= chunk_size <= MAX_CHUNK_SIZE:
        raise ValueError(f"chunk_size ({chunk_size}) fora do intervalo seguro (100 - {MAX_CHUNK_SIZE}).")
    if not 0 <= chunk_overlap < chunk_size:
        raise ValueError("chunk_overlap deve ser maior/igual a zero e estritamente menor que chunk_size.")

    _validar_pdf(temp_path)

    logger.info(
        "[BUILD_INDEX][%s] Início | uc=%s | temp_path=%s | chunk_size=%d | chunk_overlap=%d",
        clean_filename,
        uc,
        "***_tmp.pdf", # Mascarado por segurança
        chunk_size,
        chunk_overlap,
    )

    # 1) Carregar texto base do PDF
    t0_load = time.perf_counter()
    loader = PyMuPDFLoader(temp_path)
    documentos = loader.load()

    # Injetar metadata_extra nos documentos base
    if metadata_extra:
        for doc in documentos:
            doc.metadata.update(metadata_extra)

    logger.info(
        "[BUILD_INDEX][%s] Texto base carregado | paginas=%d | tempo=%.2fs",
        clean_filename,
        len(documentos),
        time.perf_counter() - t0_load,
    )

    # Botão de Pânico gerido de forma mais robusta (Lê a variável global)
    USAR_OCR = leitor_ocr is not None

    # 2) OCR das imagens embebidas
    total_imagens = 0
    total_imagens_ocr = 0
    total_paginas_com_ocr = 0

    t0_ocr = time.perf_counter()
    
    if USAR_OCR:
        try:
            with fitz.open(temp_path) as pdf_document:
                total_paginas_pdf = len(pdf_document)
                logger.info(
                    "[BUILD_INDEX][%s] OCR iniciado | paginas_pdf=%d",
                    clean_filename,
                    total_paginas_pdf,
                )

                for page_num in range(total_paginas_pdf):
                    t0_page = time.perf_counter()
                    pagina_fitz = pdf_document[page_num]
                    lista_imagens = pagina_fitz.get_images(full=True)
                    total_imagens += len(lista_imagens)

                    texto_ocr_pagina = []

                    for img_idx, img in enumerate(lista_imagens, start=1):
                        xref = img[0]
                        base_image = pdf_document.extract_image(xref)

                        largura = base_image.get("width", 0)
                        altura = base_image.get("height", 0)

                        if largura < 100 or altura < 100:
                            continue

                        try:
                            resultados_ocr = leitor_ocr.readtext(base_image["image"], detail=0)
                            texto_extraido = "\n".join(resultados_ocr).strip()

                            if texto_extraido:
                                texto_ocr_pagina.append(texto_extraido)
                                total_imagens_ocr += 1

                        except Exception as exc:
                            logger.warning("Erro OCR numa imagem do PDF %s | página=%d. Erro ignorado.", clean_filename, page_num + 1)

                    if texto_ocr_pagina and page_num < len(documentos):
                        texto_junto = (
                            "\n\n[TEXTO EXTRAÍDO DE IMAGENS NESTA PÁGINA]:\n"
                            + "\n---\n".join(texto_ocr_pagina)
                        )
                        documentos[page_num].page_content += texto_junto
                        total_paginas_com_ocr += 1

        except Exception as exc:
            logger.error(
                "Falha ao fazer OCR no PDF %s, a prosseguir com texto nativo. Erro: %s",
                clean_filename,
                type(exc).__name__,
            )
    else:
        logger.info("[BUILD_INDEX][%s] ⚠️ OCR IGNORADO (USAR_OCR=False ou motor desligado). A saltar diretamente para o Chunking...", clean_filename)


    # 3) Cabeçalhos para citação
    for doc in documentos:
        pagina_humana = doc.metadata.get("page", 0) + 1
        doc.page_content = f"[CABEÇALHO FONTE: {clean_filename}:{pagina_humana}]\n{doc.page_content}"

    # 4) Chunking
    text_splitter = RecursiveCharacterTextSplitter(
        chunk_size=chunk_size,
        chunk_overlap=chunk_overlap,
        separators=["\n\n", "\n", ". ", " ", ""],
    )
    chunks = text_splitter.split_documents(documentos)

    logger.info(
        "[BUILD_INDEX][%s] Chunking concluído | chunks=%d",
        clean_filename,
        len(chunks),
    )

    # 5) Persistência FAISS Segura
    db_path = pasta_faiss_canonica_uc(uc)
    os.makedirs(db_path, exist_ok=True)
    index_path = db_path / FAISS_INDEX_FILE

    _validar_faiss_dir(str(db_path))

    if index_path.exists():
        logger.info("[BUILD_INDEX][%s] Índice FAISS existente detetado. A carregar...", clean_filename)

        vs = FAISS.load_local(
            str(db_path),
            embeddings_model,
            allow_dangerous_deserialization=True, # Mantemos por dependência técnica do LangChain, mas mitigado pelo _validar_faiss_dir()
        )
        vs.add_documents(chunks)
    else:
        logger.info("[BUILD_INDEX][%s] Sem índice FAISS anterior. A criar novo...", clean_filename)
        vs = FAISS.from_documents(chunks, embeddings_model)

    # Criação atómica em pasta temporária usando UUID para evitar Race Conditions
    temp_id = uuid.uuid4().hex
    temp_db_path = f"{db_path}_tmp_{temp_id}"
    
    vs.save_local(temp_db_path)

    # Versionamento simplificado: em vez de renomear a pasta inteira, movemos o ficheiro de index
    if db_path.exists():
        # Aqui, idealmente, num sistema mais complexo guardar-se-ia a versão antiga
        shutil.copytree(temp_db_path, db_path, dirs_exist_ok=True)
        shutil.rmtree(temp_db_path)
    else:
        os.rename(temp_db_path, db_path)

    logger.info("[BUILD_INDEX][%s] Persistência FAISS concluída", clean_filename)

    # 6) Manifest Seguro
    manifest = {
        "uc": uc,
        "context_id": metadata_extra.get("context_id") if metadata_extra else None,
        "context_type": metadata_extra.get("context_type") if metadata_extra else None,
        "filename": clean_filename,
        "version": temp_id,
        "updated_at": datetime.datetime.now(datetime.timezone.utc).isoformat(),
        "chunks": len(chunks)
    }

    manifest_path = db_path / MANIFEST_FILE
    with manifest_path.open("w", encoding="utf-8") as f:
        json.dump(manifest, f, ensure_ascii=False, indent=2)

    # Sincronização com HF Hub (best-effort, não bloqueia em caso de falha)
    _sincronizar_uc_para_hub(uc, db_path)

    logger.info(
        "[BUILD_INDEX][%s] Fim | tempo_total=%.2fs",
        clean_filename,
        time.perf_counter() - t0_total,
    )

    return len(chunks), chunks
