import os
import shutil
import tempfile
import asyncio
import aiofiles
from typing import List, Optional
from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile
from fastapi.security import APIKeyHeader

from config import settings, FAISS_INDEX_FILE, UCEnum, logger
from core.cache import faiss_cache, bm25_cache, docs_cache, semantic_cache, _ingestao_locks
from core.retrieval import get_vector_store
from core.utils import limpar_nome_uc
from core.ml_models import executor
from services.ingestao import build_index

router = APIRouter()
api_key_header = APIKeyHeader(name="X-API-Key", auto_error=True)

def exigir_professor(chave: str = Depends(api_key_header)) -> None:
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
    
    if not os.path.exists(db_path): 
        raise HTTPException(status_code=404)
        
    shutil.rmtree(db_path)
    faiss_cache.pop(uc_limpa, None)
    bm25_cache.pop(uc_limpa, None)
    
    # Onde guardamos os ficheiros de acesso rápido (na pasta public do Laravel)
    laravel_public_dir = os.path.abspath(os.path.join(os.getcwd(), "..", "tuts-core", "public", "pdfs"))
    
    documentos_desta_uc = docs_cache.get(uc_limpa, [])
    ficheiros_a_apagar = set()
    
    for doc in documentos_desta_uc:
        conteudo = doc.page_content
        if conteudo.startswith("[CABEÇALHO FONTE:"):
            primeira_linha = conteudo.split('\n')[0]
            partes = primeira_linha.replace("[CABEÇALHO FONTE: ", "").replace("]", "").split(":")
            if len(partes) >= 2:
                nome_ficheiro = ":".join(partes[:-1]).strip()
                ficheiros_a_apagar.add(nome_ficheiro)

    for ficheiro in ficheiros_a_apagar:
        caminho_completo = os.path.join(laravel_public_dir, ficheiro)
        if os.path.exists(caminho_completo):
            try:
                os.remove(caminho_completo)
            except Exception as e:
                logger.error(f"Erro ao remover PDF {ficheiro}: {e}")

    docs_cache.pop(uc_limpa, None)
    semantic_cache.pop(uc_limpa, None)
    
    return {"mensagem": "Removido com sucesso (FAISS e PDFs do Laravel)."}

@router.post("/ingestao", dependencies=[Depends(exigir_professor)], tags=["Professores"])
async def ingestao(
    uc: UCEnum = Form(...),
    chunk_size: int = Form(1200),
    chunk_overlap: int = Form(250),
    files: List[UploadFile] = File(...)
):
    uc_limpa = limpar_nome_uc(uc.value)
    if not uc_limpa: raise HTTPException(status_code=400)

    conteudos = []
    for file in files:
        conteudo = await file.read()
        if len(conteudo) > settings.max_upload_mb * 1024 * 1024: raise HTTPException(status_code=413)

        if not conteudo.startswith(b"%PDF"):
            raise HTTPException(status_code=415, detail=f"'{file.filename}' não é um PDF válido.")

        conteudos.append((file.filename, conteudo))

    if uc_limpa not in _ingestao_locks:
        _ingestao_locks[uc_limpa] = asyncio.Lock()

    # 🚀 O SEGREDO 1: Pasta PUBLIC do Laravel! Nada de atalhos /storage no Docker.
    laravel_public_dir = os.path.abspath(os.path.join(os.getcwd(), "..", "tuts-core", "public", "pdfs"))
    os.makedirs(laravel_public_dir, exist_ok=True)

    resultados, novos_chunks, loop = [], [], asyncio.get_running_loop()
    
    async with _ingestao_locks[uc_limpa]:
        for filename, conteudo in conteudos:
            # 🚀 O SEGREDO 2: O nome com os espaços trocados por underscore!
            clean_filename = os.path.basename(filename).replace(" ", "_")

            # Guardar diretamente no public/pdfs
            caminho_laravel = os.path.join(laravel_public_dir, clean_filename)
            try:
                async with aiofiles.open(caminho_laravel, "wb") as f_dest:
                    await f_dest.write(conteudo)
            except Exception as e:
                logger.error(f"Erro ao guardar PDF no Laravel: {e}")

            # Indexação no FAISS
            tmp_fd, temp_path = tempfile.mkstemp(suffix=".pdf")
            try:
                os.close(tmp_fd)
                async with aiofiles.open(temp_path, "wb") as buf: await buf.write(conteudo)
                total_chunks, chunks = await loop.run_in_executor(
                    executor, build_index, temp_path, clean_filename, uc_limpa, 
                    chunk_size, chunk_overlap
                )
                novos_chunks.extend(chunks)
                resultados.append({"ficheiro": clean_filename, "status": "sucesso"})
            except Exception as e: 
                resultados.append({"ficheiro": clean_filename, "status": "erro", "detalhe": str(e)})
            finally:
                if os.path.exists(temp_path): os.remove(temp_path)

    faiss_cache.pop(uc_limpa, None)
    bm25_cache.pop(uc_limpa, None)
    if novos_chunks: docs_cache.setdefault(uc_limpa, []).extend(novos_chunks)

    return {"mensagem": "Ingestão processada", "resultados": resultados}