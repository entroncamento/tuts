import os
import httpx
from contextlib import asynccontextmanager
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from fastapi import BackgroundTasks
from config import settings, logger
from core.ml_models import executor
from core.cache import init_redis_index
from routers.sistema import router as router_sistema
from routers.professores import router as router_professores
from routers.alunos import router as router_alunos

@asynccontextmanager
async def lifespan(app: FastAPI):
    await init_redis_index()
    app.state.http_client = httpx.AsyncClient()
    logger.info("TUT's RAG API iniciado.")
    yield
    executor.shutdown(wait=True)
    await app.state.http_client.aclose()
    logger.info("Servidor encerrado.")

app = FastAPI(title="TUT's RAG API", lifespan=lifespan)

# ── CORS ──────────────────────────────────────────────────────────────────────
_allowed_origins = [o.strip() for o in settings.frontend_origin.split(",") if o.strip()]

app.add_middleware(
    CORSMiddleware,
    allow_origins=_allowed_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ── SERVIR PDFs ESTÁTICOS ─────────────────────────────────────────────────────
_pdf_dir = os.path.abspath(os.path.join(os.getcwd(), "..", "tuts-core", "storage", "app", "public", "pdfs"))
os.makedirs(_pdf_dir, exist_ok=True)
app.mount("/pdfs", StaticFiles(directory=_pdf_dir), name="pdfs")

# ── ROUTERS ───────────────────────────────────────────────────────────────────
app.include_router(router_sistema)
app.include_router(router_professores)
app.include_router(router_alunos)

if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host=settings.server_host, port=settings.server_port, reload=True)