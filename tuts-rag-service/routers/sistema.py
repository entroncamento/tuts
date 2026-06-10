from fastapi import APIRouter, Depends, HTTPException, Header
from config import settings
from core.cache import faiss_cache, redis_client

router = APIRouter()

# ── DEPENDENCIA DE SEGURANÇA ──────────────────────────────────────────────────
async def verificar_token_interno(x_internal_token: str = Header(None)):
    """
    Garante que o pedido de monitorização vem de uma fonte autorizada (ex: Laravel).
    """
    if not x_internal_token or x_internal_token != settings.internal_token:
        raise HTTPException(status_code=403, detail="Acesso não autorizado.")
    return True

# ── ROTAS ─────────────────────────────────────────────────────────────────────

@router.get("/health", tags=["Sistema"])
async def health_publico():
    """
    Endpoint público e minimalista. 
    Serve apenas para o Docker/Load Balancer saber que a API não crashou.
    Zero exposição de infraestrutura.
    """
    return {"status": "ok"}


@router.get("/internal/health", tags=["Sistema"], dependencies=[Depends(verificar_token_interno)])
async def health_interno():
    """
    Endpoint protegido para monitorização real da infraestrutura.
    Acessível apenas com o X-Internal-Token correto.
    """
    redis_ok = True
    try:
        if redis_client:
            await redis_client.ping()
        else:
            redis_ok = False
    except Exception:
        redis_ok = False

    return {
        "status": "ok" if redis_ok else "degradado",
        "redis_ok": redis_ok,
        "ucs_em_cache": list(faiss_cache.keys()),
    }