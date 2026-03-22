import httpx
from contextlib import asynccontextmanager
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from slowapi.errors import RateLimitExceeded
from slowapi import _rate_limit_exceeded_handler

from config import settings, logger
from database import init_db
from core.ml_models import executor
from routers.sistema import router as router_sistema
from routers.professores import router as router_professores
from routers.alunos import router as router_alunos

@asynccontextmanager
async def lifespan(app: FastAPI):
    init_db(settings.sqlite_db)
    app.state.http_client = httpx.AsyncClient()
    logger.info("A API TUT'S está Online e Pronta!")
    yield
    await app.state.http_client.aclose()
    executor.shutdown(wait=True)
    logger.info("Servidor encerrado.")

app = FastAPI(title="TUT's RAG API", lifespan=lifespan)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

from config import limiter
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)

# Ligar as rotas
app.include_router(router_sistema)
app.include_router(router_professores)
app.include_router(router_alunos)

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host=settings.server_host, port=settings.server_port)