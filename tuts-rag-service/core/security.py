import secrets

from fastapi import Header, HTTPException, Request

from config import logger, settings


def _allowed_internal_hosts() -> set[str]:
    return {
        host.strip()
        for host in (settings.internal_allowed_hosts or "").split(",")
        if host.strip()
    }


def validar_token_interno(token: str | None, source: str = "unknown") -> None:
    esperado = (settings.internal_token or "").strip()
    recebido = (token or "").strip()

    if not esperado or not secrets.compare_digest(recebido, esperado):
        logger.warning("[SECURITY] Internal token rejected | source=%s", source)
        raise HTTPException(status_code=403, detail="Acesso nao autorizado.")


def validar_host_interno(request: Request, source: str = "unknown") -> None:
    allowed_hosts = _allowed_internal_hosts()

    if not allowed_hosts:
        return

    client_host = request.client.host if request.client else ""

    if client_host not in allowed_hosts:
        logger.warning(
            "[SECURITY] Internal host rejected | source=%s | host=%s",
            source,
            client_host or "unknown",
        )
        raise HTTPException(status_code=403, detail="Endpoint restrito a chamadas internas.")


async def verificar_token_interno(
    request: Request,
    x_internal_token: str | None = Header(None),
) -> bool:
    validar_token_interno(x_internal_token, source=str(request.url.path))
    validar_host_interno(request, source=str(request.url.path))

    return True
