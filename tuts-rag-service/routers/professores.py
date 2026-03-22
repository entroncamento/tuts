import os
import shutil
import tempfile
import asyncio
import aiofiles
from typing import Annotated, List
from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile
from fastapi.security import APIKeyHeader

from config import settings, FAISS_INDEX_FILE, UCEnum, logger
from core.cache import faiss_cache, bm25_cache, docs_cache, semantic_cache, _ingestao_locks
from core.retrieval import get_vector_store
from core.utils import limpar_nome_uc
from core.ml_models import executor
from services.ingestao import build_index
import magic

router = APIRouter()
api_key_header = APIKeyHeader(name="X-API-Key", auto_error=True)

def exigir_professor(chave: Annotated[str, Depends(api_key_header)]) -> None:
    if chave.strip() != settings.professor_api_key.strip():
        raise HTTPException(status_code=403, detail="Acesso reservado.")

@router.get("/ucs", dependencies=[Depends(exigir_professor)], tags=["Professores"])
async def listar_ucs():
    base = settings.base_faiss_dir
    if not os.path.exists(base): return {"ucs": []}
    resultado = []
    for nome_uc in os.listdir(base):
        cam = os.path.join(base, nome_uc)
        if os.path.isdir(cam) and os.path.exists(os.path.join(cam, FAISS_INDEX_FILE)):
            vs = await get_vector_store(nome_uc)
            resultado.append({"uc": nome_uc, "chunks": len(docs_cache.get(nome_uc, [])) if vs else 0})
    return {"ucs": resultado}

@router.delete("/ucs/{uc}/conteudo", dependencies=[Depends(exigir_professor)], tags=["Professores"])
async def apagar_conteudo_uc(uc: str):
    uc_limpa = limpar_nome_uc(uc)
    db_path = os.path.join(settings.base_faiss_dir, uc_limpa)
    if not os.path.exists(db_path): raise HTTPException(status_code=404)
    shutil.rmtree(db_path)
    faiss_cache.pop(uc_limpa, None)
    bm25_cache.pop(uc_limpa, None)
    docs_cache.pop(uc_limpa, None)
    semantic_cache.pop(uc_limpa, None)
    return {"mensagem": "Removido com sucesso."}

@router.post("/ingestao", dependencies=[Depends(exigir_professor)], tags=["Professores"])
async def ingestao(
    files: Annotated[List[UploadFile], File(...)], uc: Annotated[UCEnum, Form(...)],
    chunk_size: Annotated[int | None, Form()] = None, chunk_overlap: Annotated[int | None, Form()] = None
):
    uc_limpa = limpar_nome_uc(uc.value)
    if not uc_limpa: raise HTTPException(status_code=400)
    
    conteudos = []
    for file in files:
        conteudo = await file.read()
        if len(conteudo) > settings.max_upload_mb * 1024 * 1024: raise HTTPException(status_code=413)
        conteudos.append((file.filename, conteudo))

    resultados, novos_chunks, loop = [], [], asyncio.get_running_loop()
    async with _ingestao_locks[uc_limpa]:
        for filename, conteudo in conteudos:
            tmp_fd, temp_path = tempfile.mkstemp(suffix=".pdf")
            try:
                os.close(tmp_fd)
                async with aiofiles.open(temp_path, "wb") as buf: await buf.write(conteudo)
                total_chunks, chunks = await loop.run_in_executor(executor, build_index, temp_path, filename, uc_limpa, chunk_size or settings.chunk_size, chunk_overlap or settings.chunk_overlap)
                novos_chunks.extend(chunks)
                resultados.append({"ficheiro": filename, "status": "sucesso"})
            except Exception as e: resultados.append({"ficheiro": filename, "status": "erro", "detalhe": str(e)})
            finally:
                if os.path.exists(temp_path): os.remove(temp_path)

        faiss_cache.pop(uc_limpa, None)
        bm25_cache.pop(uc_limpa, None)
        if novos_chunks: docs_cache.setdefault(uc_limpa, []).extend(novos_chunks)

    return {"mensagem": "Ingestão processada", "resultados": resultados}