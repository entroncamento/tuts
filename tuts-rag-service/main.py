from contextlib import asynccontextmanager
from pathlib import Path

import httpx
from fastapi import FastAPI, Depends, HTTPException, Header
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse

from config import settings, logger
from core.ml_models import executor
from core.cache import init_redis_index
from routers.sistema import router as router_sistema
from routers.professores import router as router_professores
from routers.alunos import router as router_alunos

# ── DEPENDENCIA DE SEGURANÇA (EXEMPLO) ────────────────────────────────────────
async def verificar_token_interno(x_internal_token: str = Header(None)):
    """
    Garante que o pedido vem do Laravel ou de um cliente autorizado.
    """
    if not x_internal_token or x_internal_token != settings.internal_api_token:
        raise HTTPException(status_code=403, detail="Acesso não autorizado.")
    return True

@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("A inicializar índice Redis...")
    await init_redis_index()

    app.state.http_client = httpx.AsyncClient()

    logger.info("TUT's RAG API iniciado.")
    logger.info("Frontend origins permitidos: %s", settings.frontend_origin)
    
    # Mascarar os caminhos absolutos nos logs por segurança
    faiss_name = Path(settings.base_faiss_dir).name
    pdf_name = Path(settings.pdf_storage_dir).name
    logger.info("Pasta FAISS: [PROTECTED]/%s", faiss_name)
    logger.info("Pasta PDFs: [PROTECTED]/%s", pdf_name)

    yield

    logger.info("A encerrar executor...")
    executor.shutdown(wait=True)

    logger.info("A fechar cliente HTTP...")
    await app.state.http_client.aclose()

    logger.info("Servidor encerrado.")


app = FastAPI(title="TUT's RAG API", lifespan=lifespan)

_allowed_origins = [o.strip() for o in settings.frontend_origin.split(",") if o.strip()]

# ── SEGURANÇA CORS ────────────────────────────────────────────────────────────
app.add_middleware(
    CORSMiddleware,
    allow_origins=_allowed_origins,
    allow_credentials=True,
    allow_methods=["GET", "POST", "OPTIONS"], # Limitado aos métodos essenciais
    allow_headers=["Content-Type", "Authorization", "X-Internal-Token"], # Limitado a headers conhecidos
)

# ── SERVIR PDFs (VERSÃO SEGURA/AUTENTICADA) ───────────────────────────────────
pdf_dir = Path(settings.pdf_storage_dir).resolve()

if not pdf_dir.exists():
    logger.warning("[PDFS] Pasta de PDFs não existe. A criar em modo seguro.")
    pdf_dir.mkdir(parents=True, exist_ok=True)

# Endpoint seguro em vez de StaticFiles
@app.get("/pdfs/{filename}", dependencies=[Depends(verificar_token_interno)])
async def obter_pdf_seguro(filename: str):
    """
    Serve PDFs apenas a quem apresentar o token interno correto no Header.
    """
    safe_name = Path(filename).name # Impede Path Traversal (ex: ../../../etc/passwd)
    path = pdf_dir / safe_name

    if not path.exists() or not path.is_file():
        raise HTTPException(status_code=404, detail="Ficheiro não encontrado.")

    return FileResponse(path)

# ── ROUTERS ───────────────────────────────────────────────────────────────────
app.include_router(router_sistema)
app.include_router(router_professores)
app.include_router(router_alunos)

if __name__ == "__main__":
    import uvicorn
    
    # ATENÇÃO: Garante que settings.server_host está configurado como "127.0.0.1" 
    # no teu ficheiro .env de produção!
    uvicorn.run(
        "main:app",
        host=settings.server_host,
        port=settings.server_port,
        reload=False,
    )