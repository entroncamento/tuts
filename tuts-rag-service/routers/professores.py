import os
import re
import shutil
import tempfile
import asyncio
import aiofiles
from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile
from fastapi.security import APIKeyHeader

from config import settings, FAISS_INDEX_FILE, logger
from core.cache import faiss_cache, bm25_cache, docs_cache, _ingestao_locks
from core.retrieval import get_vector_store
from core.utils import limpar_nome_uc
from core.ml_models import executor
from services.ingestao import build_index

router = APIRouter()
api_key_header = APIKeyHeader(name="X-API-Key", auto_error=True)

# 🚀 Constante Global Corrigida: Aponta para a pasta Storage do Laravel!
_LARAVEL_PDF_DIR = os.path.abspath(
    os.path.join(os.getcwd(), "..", "tuts-core", "storage", "app", "public", "pdfs")
)


def exigir_professor(chave: str = Depends(api_key_header)) -> None:
    if chave.strip() != settings.professor_api_key.strip():
        raise HTTPException(status_code=403, detail="Acesso reservado. Chave de API inválida.")


@router.get("/ucs", dependencies=[Depends(exigir_professor)], tags=["Professores"])
async def listar_ucs():
    base = settings.base_faiss_dir
    if not os.path.exists(base):
        return {"ucs": []}
    
    resultado = []
    for nome_uc in os.listdir(base):
        cam = os.path.join(base, nome_uc)
        if os.path.isdir(cam) and os.path.exists(os.path.join(cam, FAISS_INDEX_FILE)):
            vs = await get_vector_store(nome_uc)
            resultado.append({
                "uc": nome_uc, 
                "chunks": len(docs_cache.get(nome_uc, [])) if vs else 0
            })
    return {"ucs": resultado}


@router.delete("/ucs/{uc}/conteudo", dependencies=[Depends(exigir_professor)], tags=["Professores"])
async def apagar_conteudo_uc(uc: str):
    uc_limpa = limpar_nome_uc(uc)
    db_path = os.path.join(settings.base_faiss_dir, uc_limpa)
    
    if not os.path.exists(db_path): 
        raise HTTPException(status_code=404, detail=f"A UC '{uc}' não foi encontrada na base de dados.")
        
    shutil.rmtree(db_path)
    faiss_cache.pop(uc_limpa, None)
    bm25_cache.pop(uc_limpa, None)
    docs_cache.pop(uc_limpa, None)
    
    # 🔥 CORREÇÃO: Apagar os PDFs baseados no prefixo da UC
    if os.path.exists(_LARAVEL_PDF_DIR):
        for ficheiro in os.listdir(_LARAVEL_PDF_DIR):
            if ficheiro.startswith(f"{uc_limpa}_"):
                caminho_completo = os.path.join(_LARAVEL_PDF_DIR, ficheiro)
                try:
                    os.remove(caminho_completo)
                except Exception as e:
                    logger.error("Erro ao remover PDF %s: %s", ficheiro, e)
    
    return {"mensagem": f"Conteúdo da UC '{uc_limpa}' removido com sucesso (FAISS e PDFs)."}


@router.post("/ingestao", dependencies=[Depends(exigir_professor)], tags=["Professores"])
async def ingestao(
    uc: str = Form(...),
    chunk_size: int = Form(1200),
    chunk_overlap: int = Form(250),
    files: list[UploadFile] = File(...)
):
    uc_limpa = limpar_nome_uc(uc)
    if not uc_limpa: 
        raise HTTPException(status_code=400, detail="Nome de UC inválido ou vazio.")

    conteudos = []
    for file in files:
        conteudo = await file.read()
        if len(conteudo) > settings.max_upload_mb * 1024 * 1024: 
            raise HTTPException(
                status_code=413, 
                detail=f"O ficheiro '{file.filename}' excede o limite de {settings.max_upload_mb} MB."
            )

        if not conteudo.startswith(b"%PDF"):
            raise HTTPException(
                status_code=415, 
                detail=f"O ficheiro '{file.filename}' não é um PDF válido."
            )

        conteudos.append((file.filename, conteudo))

    os.makedirs(_LARAVEL_PDF_DIR, exist_ok=True)

    resultados, novos_chunks = [], []
    loop = asyncio.get_running_loop()
    
    async with _ingestao_locks[uc_limpa]:
        for filename, conteudo in conteudos:
            base_name = os.path.basename(filename).replace(" ", "_")
            
            # 🔥 CORREÇÃO: Adicionar prefixo da UC ao nome do ficheiro para evitar colisões
            clean_filename = f"{uc_limpa}_{re.sub(r'[^a-zA-Z0-9_.-]', '', base_name)}"

            caminho_laravel = os.path.join(_LARAVEL_PDF_DIR, clean_filename)
            try:
                async with aiofiles.open(caminho_laravel, "wb") as f_dest:
                    await f_dest.write(conteudo)
            except Exception as e:
                logger.error("Erro ao guardar PDF %s no Laravel: %s", clean_filename, e)

            tmp_fd, temp_path = tempfile.mkstemp(suffix=".pdf")
            try:
                os.close(tmp_fd)
                async with aiofiles.open(temp_path, "wb") as buf: 
                    await buf.write(conteudo)
                
                total_chunks, chunks = await loop.run_in_executor(
                    executor, build_index, temp_path, clean_filename, uc_limpa, 
                    chunk_size, chunk_overlap
                )
                novos_chunks.extend(chunks)
                resultados.append({"ficheiro": clean_filename, "status": "sucesso", "chunks": total_chunks})
            except Exception as e: 
                resultados.append({"ficheiro": clean_filename, "status": "erro", "detalhe": str(e)})
            finally:
                if os.path.exists(temp_path): 
                    os.remove(temp_path)

    faiss_cache.pop(uc_limpa, None)
    bm25_cache.pop(uc_limpa, None)
    if novos_chunks: 
        docs_cache.setdefault(uc_limpa, []).extend(novos_chunks)

    return {"mensagem": "Ingestão processada", "resultados": resultados}