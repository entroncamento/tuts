import asyncio
import time
from enum import Enum
from typing import Callable, Optional

from config import logger


class CircuitState(str, Enum):
    CLOSED = "closed"
    OPEN = "open"
    HALF_OPEN = "half_open"


# Armazenamento em memória para substituir o Redis (Estado Global)
_memory_store = {}


class CircuitBreaker:
    def __init__(
        self,
        name: str,
        threshold: int = 5,
        recovery_timeout: int = 30,
        window: int = 60,
    ):
        self.name = f"circuit:{name}"
        self.threshold = threshold
        self.recovery_timeout = recovery_timeout
        self.window = window

    async def get_state(self) -> CircuitState:
        state_key = f"{self.name}:state"
        state_str = _memory_store.get(state_key, CircuitState.CLOSED.value)
        
        if state_str == CircuitState.OPEN.value:
            last_failure_key = f"{self.name}:last_failure"
            last_failure_time = _memory_store.get(last_failure_key)
            
            if last_failure_time:
                if time.time() - float(last_failure_time) > self.recovery_timeout:
                    await self._set_state(CircuitState.HALF_OPEN)
                    return CircuitState.HALF_OPEN
            return CircuitState.OPEN
        
        return CircuitState(state_str)

    async def _set_state(self, state: CircuitState):
        state_key = f"{self.name}:state"
        _memory_store[state_key] = state.value
        logger.info("[CIRCUIT][%s] Transição para estado: %s", self.name, state.value)

    async def report_success(self):
        state = await self.get_state()
        if state == CircuitState.HALF_OPEN:
            await self._set_state(CircuitState.CLOSED)
            _memory_store.pop(f"{self.name}:failures", None)
        elif state == CircuitState.CLOSED:
            _memory_store.pop(f"{self.name}:failures", None)

    async def report_failure(self):
        _memory_store[f"{self.name}:last_failure"] = str(time.time())
        
        state = await self.get_state()
        if state == CircuitState.HALF_OPEN or state == CircuitState.OPEN:
            await self._set_state(CircuitState.OPEN)
            return

        failures_key = f"{self.name}:failures"
        now = time.time()
        
        data = _memory_store.get(failures_key)
        
        if not data or data["expires_at"] < now:
            # Novo contador ou janela expirada
            data = {"count": 1, "expires_at": now + self.window}
        else:
            data["count"] += 1
            
        _memory_store[failures_key] = data
        
        if data["count"] >= self.threshold:
            await self._set_state(CircuitState.OPEN)

    async def call(self, func: Callable, *args, **kwargs):
        state = await self.get_state()
        
        if state == CircuitState.OPEN:
            logger.warning("[CIRCUIT][%s] Circuito aberto. Fast-fail.", self.name)
            raise RuntimeError(f"Circuit {self.name} is OPEN")

        try:
            result = await func(*args, **kwargs)
            await self.report_success()
            return result
        except Exception as e:
            await self.report_failure()
            raise e

    async def call_generator(self, func: Callable, *args, **kwargs):
        state = await self.get_state()
        
        if state == CircuitState.OPEN:
            logger.warning("[CIRCUIT][%s] Circuito aberto (Stream). Fast-fail.", self.name)
            raise RuntimeError(f"Circuit {self.name} is OPEN")

        try:
            async for chunk in func(*args, **kwargs):
                yield chunk
            await self.report_success()
        except Exception as e:
            await self.report_failure()
            raise e
