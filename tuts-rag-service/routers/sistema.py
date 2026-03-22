from fastapi import APIRouter
from core.cache import faiss_cache

router = APIRouter()

@router.get("/health", tags=["Sistema"])
async def health():
    return {
        "status": "ok",
        "modelos_carregados": True,
        "ucs_em_cache": list(faiss_cache.keys()),
    }