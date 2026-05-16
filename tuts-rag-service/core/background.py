import asyncio
from typing import Coroutine, Any, Set
from config import logger

# ── LIMITES DE SEGURANÇA ──────────────────────────────────────────────────────
MAX_CONCURRENT_TASKS = 20
TASK_TIMEOUT_SECONDS = 60

# Estado Global para rastreamento (Graceful Shutdown)
_pending_tasks: Set[asyncio.Task] = set()
_semaphore: asyncio.Semaphore | None = None

def _get_semaphore() -> asyncio.Semaphore:
    """
    Instancia o semáforo de forma preguiçosa (lazy) para garantir que
    é criado dentro do event loop atual e evitar erros do asyncio.
    """
    global _semaphore
    if _semaphore is None:
        _semaphore = asyncio.Semaphore(MAX_CONCURRENT_TASKS)
    return _semaphore

async def _run_with_limits(coro: Coroutine[Any, Any, Any], nome: str) -> None:
    """
    Executa a coroutine com limitação de concorrência e um limite estrito de tempo.
    """
    sem = _get_semaphore()
    try:
        async with sem:
            await asyncio.wait_for(coro, timeout=TASK_TIMEOUT_SECONDS)
    except asyncio.TimeoutError:
        logger.error("[BACKGROUND] Tarefa '%s' abortada: Excedeu o timeout de %ds.", nome, TASK_TIMEOUT_SECONDS)
    except asyncio.CancelledError:
        logger.warning("[BACKGROUND] Tarefa '%s' cancelada pelo sistema.", nome)
    except Exception as exc:
        logger.error("[BACKGROUND] Tarefa '%s' falhou: %s", nome, type(exc).__name__)

def disparar_background(coro: Coroutine[Any, Any, Any], nome: str) -> None:
    """
    Dispara uma tarefa em background de forma controlada.
    """
    try:
        loop = asyncio.get_running_loop()
    except RuntimeError:
        logger.error("[BACKGROUND] Falha ao disparar '%s': Nenhum event loop em execução.", nome)
        return

    # Cria a tarefa e envolve-a com os nossos limites
    task = loop.create_task(_run_with_limits(coro, nome))
    
    # Adiciona ao registo de tarefas ativas
    _pending_tasks.add(task)
    
    # Quando a tarefa termina (com sucesso ou erro), remove do registo para evitar Memory Leaks
    task.add_done_callback(_pending_tasks.discard)

async def aguardar_tarefas_background() -> None:
    """
    Dica: Chama esta função no `lifespan` do teu `main.py` durante o encerramento do servidor.
    Exemplo: await aguardar_tarefas_background()
    """
    if not _pending_tasks:
        return
    
    logger.info("[BACKGROUND] A aguardar a conclusão de %d tarefas em background...", len(_pending_tasks))
    
    # Aguarda no máximo 5 segundos para não prender o shutdown do servidor
    _, pending = await asyncio.wait(_pending_tasks, timeout=5.0)
    
    if pending:
        logger.warning("[BACKGROUND] %d tarefas abortadas devido ao encerramento do servidor.", len(pending))
        for t in pending:
            t.cancel()