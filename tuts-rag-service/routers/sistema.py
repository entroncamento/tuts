from fastapi import APIRouter, Depends, HTTPException

from config import logger, settings
from core.cache import faiss_cache, redis_client
from core.faiss_health import validate_faiss_indexes
from core.security import verificar_token_interno

router = APIRouter()


@router.get("/health", tags=["Sistema"])
async def health_publico():
    return {"status": "ok"}


@router.get("/internal/health", tags=["Sistema"], dependencies=[Depends(verificar_token_interno)])
async def health_interno():
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


@router.get("/ready", tags=["Sistema"], dependencies=[Depends(verificar_token_interno)])
async def ready():
    faiss_status = validate_faiss_indexes(create_missing_manifests=False)

    redis_required = bool(getattr(settings, "semantic_cache_enabled", True))
    redis_ok = True

    if redis_required:
        try:
            await redis_client.ping()
        except Exception as exc:
            redis_ok = False
            logger.warning("[READY] Redis indisponivel: %s", type(exc).__name__)

    ready_ok = bool(faiss_status.get("ready")) and (redis_ok or not redis_required)

    payload = {
        "status": "ok" if ready_ok else "degradado",
        "ready": ready_ok,
        "faiss": faiss_status,
        "redis_required": redis_required,
        "redis_ok": redis_ok,
    }

    if not ready_ok:
        logger.warning(
            "[READY] readiness falhou | faiss_ready=%s | redis_required=%s | redis_ok=%s",
            faiss_status.get("ready"),
            redis_required,
            redis_ok,
        )
        raise HTTPException(status_code=503, detail=payload)

    return payload
