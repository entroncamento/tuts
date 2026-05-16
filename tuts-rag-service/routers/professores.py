import os
import re
import shutil
import tempfile
import asyncio
import time
import secrets
import fitz  # PyMuPDF para validação segura
import aiofiles
from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile, Query
from fastapi.security import APIKeyHeader

from config import settings, FAISS_INDEX_FILE, logger
from core.cache import (
    faiss_cache,
    bm25_cache,
    docs_cache,
    _ingestao_locks,
    invalidar_cache_redis_uc,
)
from core.utils import limpar_nome_uc
from core.ml_models import executor
from services.ingestao import build_index

router = APIRouter()
api_key_header = APIKeyHeader(name="X-API-Key", auto_error=True)

_LARAVEL_PDF_DIR = settings.pdf_storage_dir

# ── LIMITES DE SEGURANÇA GLOBAIS ─────────────────────────────────────────────
MAX_FILES_PER_UPLOAD = 5
CHUNK_SIZE_MIN = 200
CHUNK_SIZE_MAX = 3000

def exigir_professor(chave: str = Depends(api_key_header)) -> None:
    """Validação segura de chaves API prevenindo Timing Attacks."""
    esperado = settings.professor_api_key.strip()
    recebido = (chave or "").strip()

    if not esperado or not secrets.compare_digest(recebido, esperado):
        raise HTTPException(
            status_code=403,
            detail="Acesso reservado. Chave de API inválida.",
        )


@router.get("/ucs", dependencies=[Depends(exigir_professor)], tags=["Professores"])
async def listar_ucs():
    base = settings.base_faiss_dir
    if not os.path.exists(base):
        return {"ucs": []}

    resultado = []
    for nome_uc in os.listdir(base):
        cam = os.path.join(base, nome_uc)
        index_path = os.path.join(cam, FAISS_INDEX_FILE)

        if os.path.isdir(cam) and os.path.exists(index_path):
            pdfs = 0
            if os.path.exists(_LARAVEL_PDF_DIR):
                pdfs = len(
                    [
                        f
                        for f in os.listdir(_LARAVEL_PDF_DIR)
                        if f.startswith(f"{nome_uc}_") and f.lower().endswith(".pdf")
                    ]
                )

            resultado.append(
                {
                    "uc": nome_uc,
                    "pdfs": pdfs,
                    "chunks_em_cache": len(docs_cache.get(nome_uc, []))
                    if nome_uc in docs_cache
                    else None,
                }
            )

    return {"ucs": resultado}


@router.delete("/ucs/{uc}/conteudo", dependencies=[Depends(exigir_professor)], tags=["Professores"])
async def apagar_conteudo_uc(
    uc: str,
    confirmacao: str = Query(None, description="Escreva 'APAGAR' para confirmar a destruição de dados.")
):
    """
    Endpoint destrutivo. Requer a palavra-chave APAGAR para prevenir eliminações acidentais.
    """
    if confirmacao != "APAGAR":
        raise HTTPException(
            status_code=400,
            detail="Confirmação de segurança ausente ou inválida. Parâmetro confirmacao=APAGAR é obrigatório."
        )

    uc_limpa = limpar_nome_uc(uc)
    db_path = os.path.join(settings.base_faiss_dir, uc_limpa)
    logger.warning("### DELETE DESTRUTIVO ATIVADO ### uc=%s", uc_limpa)

    faiss_removido = False
    pdfs_removidos = 0

    if os.path.exists(db_path):
        shutil.rmtree(db_path)
        faiss_removido = True
        logger.info("[DELETE] Pasta FAISS removida: %s", db_path)

    faiss_cache.pop(uc_limpa, None)
    bm25_cache.pop(uc_limpa, None)
    docs_cache.pop(uc_limpa, None)

    if os.path.exists(_LARAVEL_PDF_DIR):
        for ficheiro in os.listdir(_LARAVEL_PDF_DIR):
            if ficheiro.startswith(f"{uc_limpa}_"):
                caminho_completo = os.path.join(_LARAVEL_PDF_DIR, ficheiro)
                try:
                    os.remove(caminho_completo)
                    pdfs_removidos += 1
                except Exception as e:
                    logger.error("Erro ao remover PDF %s: %s", ficheiro, type(e).__name__)

    await invalidar_cache_redis_uc(uc_limpa)

    if not faiss_removido and pdfs_removidos == 0:
        raise HTTPException(status_code=404, detail=f"Sem conteúdo encontrado para a UC '{uc_limpa}'.")

    return {
        "mensagem": f"Conteúdo da UC '{uc_limpa}' removido com sucesso (FAISS={faiss_removido}, PDFs={pdfs_removidos})."
    }


def _verificar_pdf_integro(caminho_pdf: str) -> None:
    """Validação síncrona rigorosa de PDF via PyMuPDF"""
    try:
        with fitz.open(caminho_pdf) as doc:
            if doc.page_count == 0:
                raise ValueError("O PDF está vazio.")
    except Exception as e:
        raise ValueError(f"Ficheiro PDF malformado ou corrompido.")


@router.post("/ingestao", dependencies=[Depends(exigir_professor)], tags=["Professores"])
async def ingestao(
    uc: str = Form(...),
    chunk_size: int = Form(1200),
    chunk_overlap: int = Form(250),
    files: list[UploadFile] = File(...),
):
    t0_total = time.perf_counter()

    # 1. Validações Iniciais (Prevenção de DoS)
    if len(files) > MAX_FILES_PER_UPLOAD:
        raise HTTPException(status_code=400, detail=f"Máximo de {MAX_FILES_PER_UPLOAD} ficheiros excedido.")

    if not CHUNK_SIZE_MIN <= chunk_size <= CHUNK_SIZE_MAX:
        raise HTTPException(status_code=400, detail=f"chunk_size deve estar entre {CHUNK_SIZE_MIN} e {CHUNK_SIZE_MAX}.")

    if not 0 <= chunk_overlap < chunk_size:
        raise HTTPException(status_code=400, detail="chunk_overlap inválido.")

    uc_limpa = limpar_nome_uc(uc)
    if not uc_limpa:
        raise HTTPException(status_code=400, detail="Nome de UC inválido.")

    os.makedirs(_LARAVEL_PDF_DIR, exist_ok=True)
    resultados = []
    ficheiros_validados = []
    loop = asyncio.get_running_loop()

    # 2. Upload com Streaming & Validação OOB (Sem bloquear o Event Loop ou usar a RAM)
    for file in files:
        if not file.filename.lower().endswith(".pdf"):
            resultados.append({"ficheiro": file.filename, "status": "erro", "detalhe": "Extensão não é PDF."})
            continue

        base_name = os.path.basename(file.filename).replace(" ", "_")
        clean_filename = f"{uc_limpa}_{re.sub(r'[^a-zA-Z0-9_.-]', '', base_name)}"
        caminho_laravel = os.path.join(_LARAVEL_PDF_DIR, clean_filename)

        if os.path.exists(caminho_laravel):
            # Não devolvemos o 'caminho_laravel' absoluto por motivos de segurança (Path Leakage)
            resultados.append({"ficheiro": clean_filename, "status": "erro", "detalhe": f"PDF com nome '{clean_filename}' já existe."})
            continue

        tmp_fd, temp_path = tempfile.mkstemp(suffix=".pdf")
        os.close(tmp_fd)

        tamanho_total = 0
        try:
            # Streaming do ficheiro em blocos de 1MB
            async with aiofiles.open(temp_path, "wb") as f_dest:
                while chunk := await file.read(1024 * 1024):
                    tamanho_total += len(chunk)
                    if tamanho_total > settings.max_upload_mb * 1024 * 1024:
                        raise ValueError(f"Ficheiro excede {settings.max_upload_mb}MB.")
                    await f_dest.write(chunk)

            # Validação estrutural do PDF 
            await loop.run_in_executor(None, _verificar_pdf_integro, temp_path)

            ficheiros_validados.append({
                "temp_path": temp_path,
                "clean_filename": clean_filename,
                "caminho_laravel": caminho_laravel
            })

        except Exception as e:
            if os.path.exists(temp_path):
                os.remove(temp_path)
            resultados.append({"ficheiro": clean_filename, "status": "erro", "detalhe": str(e)})

    if not ficheiros_validados:
        return {"mensagem": "Nenhum ficheiro válido processado.", "resultados": resultados}

    # 3. Processamento de Ingestão Sequencial (Lock Ativo)
    logger.info("[INGESTAO] Lock adquirido para a UC '%s'", uc_limpa)
    async with _ingestao_locks[uc_limpa]:
        for item in ficheiros_validados:
            temp_path = item["temp_path"]
            clean_filename = item["clean_filename"]
            caminho_laravel = item["caminho_laravel"]

            try:
                # Copia para o storage final e usa o temp para a indexação
                shutil.copy(temp_path, caminho_laravel)

                total_chunks, _ = await loop.run_in_executor(
                    executor,
                    build_index,
                    temp_path,
                    clean_filename,
                    uc_limpa,
                    chunk_size,
                    chunk_overlap,
                )

                resultados.append({"ficheiro": clean_filename, "status": "sucesso", "chunks": total_chunks})
            except Exception as e:
                logger.exception("[INGESTAO][%s] Erro no build_index", clean_filename)
                if os.path.exists(caminho_laravel):
                    os.remove(caminho_laravel)
                # Mais uma vez: Omitir detalhes do stacktrace nas respostas públicas
                resultados.append({"ficheiro": clean_filename, "status": "erro", "detalhe": "Falha na conversão vetorial."})
            finally:
                if os.path.exists(temp_path):
                    os.remove(temp_path)

    # 4. Limpeza de Caches
    faiss_cache.pop(uc_limpa, None)
    bm25_cache.pop(uc_limpa, None)
    docs_cache.pop(uc_limpa, None)

    if any(r.get("status") == "sucesso" for r in resultados):
        await invalidar_cache_redis_uc(uc_limpa)

    logger.info(
        "[INGESTAO] Fim | UC='%s' | sucessos=%d | tempo_total=%.2fs",
        uc_limpa,
        sum(1 for r in resultados if r.get("status") == "sucesso"),
        time.perf_counter() - t0_total,
    )

    return {"mensagem": "Ingestão processada", "resultados": resultados}