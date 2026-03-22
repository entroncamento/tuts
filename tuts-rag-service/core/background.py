import asyncio
from config import logger

def _cb_log_erro(task: asyncio.Future, nome: str) -> None:
    try:
        exc = task.exception()
        if exc:
            logger.warning("Tarefa background '%s' falhou: %s", nome, exc)
    except asyncio.CancelledError:
        pass

def disparar_background(future: asyncio.Future, nome: str) -> None:
    task = asyncio.ensure_future(future)
    task.add_done_callback(lambda t: _cb_log_erro(t, nome))