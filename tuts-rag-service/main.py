from contextlib import asynccontextmanager
from pathlib import Path

import httpx
from fastapi import Depends, FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse

from config import logger, settings, request_id_ctx
from core.cache import fechar_conexoes, init_redis_index
from core.ml_models import executor
from core.security import verificar_token_interno
from routers.alunos import router as router_alunos
from routers.professores import router as router_professores
from routers.sistema import router as router_sistema


@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("[STARTUP] A inicializar indice Redis/cache semantica...")
    await init_redis_index()

    app.state.http_client = httpx.AsyncClient()

    logger.info("[STARTUP] TUTS RAG API iniciado.")
    logger.info("[STARTUP] Frontend origins permitidos: %s", settings.frontend_origin)
    logger.info("[STARTUP] Pasta FAISS: [PROTECTED]/%s", Path(settings.base_faiss_dir).name)
    logger.info("[STARTUP] Pasta PDFs: [PROTECTED]/%s", Path(settings.pdf_storage_dir).name)

    yield

    logger.info("[SHUTDOWN] A encerrar executor e clientes...")
    executor.shutdown(wait=True)
    await fechar_conexoes()
    await app.state.http_client.aclose()
    logger.info("[SHUTDOWN] Servidor encerrado.")


app = FastAPI(title="TUTS RAG API", lifespan=lifespan)

@app.middleware("http")
async def add_request_id(request: Request, call_next):
    request_id = request.headers.get("X-Request-ID")
    token = request_id_ctx.set(request_id)
    try:
        response = await call_next(request)
        if request_id:
            response.headers["X-Request-ID"] = request_id
        return response
    finally:
        request_id_ctx.reset(token)

_allowed_origins = [origin.strip() for origin in settings.frontend_origin.split(",") if origin.strip()]

app.add_middleware(
    CORSMiddleware,
    allow_origins=_allowed_origins,
    allow_credentials=True,
    allow_methods=["GET", "POST", "OPTIONS"],
    allow_headers=["Content-Type", "Authorization", "X-Internal-Token", "X-API-Key"],
)

pdf_dir = Path(settings.pdf_storage_dir).resolve()

if not pdf_dir.exists():
    logger.warning("[PDFS] Pasta de PDFs nao existe. A criar em modo seguro.")
    pdf_dir.mkdir(parents=True, exist_ok=True)


@app.get("/pdfs/{filename}", dependencies=[Depends(verificar_token_interno)])
async def obter_pdf_seguro(filename: str):
    safe_name = Path(filename).name

    if safe_name != filename or not safe_name.lower().endswith(".pdf"):
        raise HTTPException(status_code=404, detail="Ficheiro nao encontrado.")

    path = pdf_dir / safe_name

    if not path.exists() or not path.is_file():
        raise HTTPException(status_code=404, detail="Ficheiro nao encontrado.")

    return FileResponse(
        path,
        media_type="application/pdf",
        headers={"X-Content-Type-Options": "nosniff"},
    )


app.include_router(router_sistema)
app.include_router(router_professores)
app.include_router(router_alunos)


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(
        "main:app",
        host="0.0.0.0",
        port=7860,
        reload=False,
    )
