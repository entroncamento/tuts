from __future__ import annotations

import asyncio
import json
import random
import re
import statistics
import time
from dataclasses import asdict, dataclass
from datetime import datetime
from pathlib import Path
from typing import Any, Callable, Coroutine, Literal

import httpx
from openai import AsyncOpenAI
from pydantic import BaseModel, ConfigDict, Field, ValidationError, model_validator
from pydantic_settings import BaseSettings, SettingsConfigDict
from tqdm import tqdm


# =============================================================================
# CONFIG — Pydantic Settings
# =============================================================================

class Config(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
        populate_by_name=True,
        case_sensitive=False,
    )

    # endpoint TUT'S
    tuts_api_url: str = "http://localhost:8001/perguntar"
    tuts_uc: str = "Tecnologias_Avancadas_para_Client-side"

    # segredos obrigatórios
    internal_token: str = Field(alias="INTERNAL_TOKEN", default="")
    groq_api_key: str = Field(alias="GROQ_API_KEY", default="")

    # juiz
    juiz_base_url: str = "https://api.groq.com/openai/v1"
    juiz_model: str = "llama-3.1-8b-instant"

    # ficheiros
    dataset_path: Path = Path("dataset_tacs.json")
    output_json_path: Path = Path("resultados_mis4tel_v2.json")
    checkpoint_jsonl_path: Path = Path("resultados_mis4tel_v2.jsonl")

    # checkpoint
    # False = limpa o checkpoint antes de cada run, para não misturares runs antigos.
    # True = retoma do checkpoint existente.
    resume_checkpoint: bool = False

    # cache do TUT'S
    # Só funciona se o endpoint /perguntar aceitar este campo; se não aceitar, é ignorado.
    bypass_cache: bool = True

    # rede / retries
    tuts_max_retries: int = 4
    juiz_max_retries: int = 3
    retry_base_sleep_s: float = 1.25
    inter_item_sleep_s: float = 1.0

    # timeouts
    tuts_timeout_connect_s: float = 10.0
    tuts_timeout_read_s: float = 180.0
    tuts_timeout_write_s: float = 20.0
    tuts_timeout_pool_s: float = 10.0
    juiz_timeout_s: float = 90.0

    # limites defensivos do avaliador
    max_sse_events: int = 5000
    max_response_chars: int = 80_000

    # bootstrap
    bootstrap_samples: int = 4000
    bootstrap_seed: int = 42

    # paralelismo
    # Para avaliação científica, manter 1 evita rate limit e torna a experiência mais estável.
    concurrency: int = 1

    # pesos para score composto
    peso_fidelidade: float = 0.40
    peso_relevancia: float = 0.35
    peso_pedagogia: float = 0.25

    @model_validator(mode="after")
    def validar_config(self) -> "Config":
        self.concurrency = max(1, int(self.concurrency))
        self.tuts_max_retries = max(1, int(self.tuts_max_retries))
        self.juiz_max_retries = max(1, int(self.juiz_max_retries))
        self.bootstrap_samples = max(500, int(self.bootstrap_samples))
        self.max_sse_events = max(100, int(self.max_sse_events))
        self.max_response_chars = max(5_000, int(self.max_response_chars))
        return self

    def validate_secrets(self) -> None:
        missing = []
        if not self.internal_token:
            missing.append("INTERNAL_TOKEN")
        if not self.groq_api_key:
            missing.append("GROQ_API_KEY")
        if missing:
            raise RuntimeError(
                f"Faltam variáveis de ambiente obrigatórias: {', '.join(missing)}"
            )


cfg = Config()


# =============================================================================
# PROMPT DO JUIZ
# =============================================================================

PROMPT_JUIZ = """
És um avaliador académico especialista em Tecnologia Educativa e sistemas RAG.

Avalia a RESPOSTA DA IA face à PERGUNTA DO ALUNO e ao CONTEXTO DO PROFESSOR.

REGRAS IMPORTANTES:
- Avalia o conteúdo factual, não a redação literal.
- Não penalizes títulos, markdown, organização visual, ou menções genéricas a material/fonte,
  exceto se isso introduzir conteúdo factual novo não suportado.
- Se a resposta for claramente uma mensagem técnica de erro, timeout, overload, rate limit,
  ou falha de serviço, devolve status_avaliacao="erro_tecnico".
- A métrica "recusa" aplica-se APENAS a perguntas fora do âmbito.
- Se a pergunta estiver dentro do âmbito, "recusa" deve ser -1.
- A fidelidade deve refletir o grau em que a resposta está ancorada no contexto dado.
- Pequenos acréscimos pedagógicos aceitáveis podem valer 3, mas não 5.
- Respostas incompletas, cortadas a meio da frase, ou que terminem abruptamente devem ser penalizadas.
- Se receberes um aviso de possível truncagem, considera-o na avaliação, mas confirma pelo conteúdo da resposta.

MÉTRICAS (0 a 5):
1. Fidelidade (Faithfulness)
   5 = Toda a informação relevante vem do contexto, sem alucinações.
   3 = Maioritariamente fiel, com pequenos acréscimos justificáveis.
   0 = Inventada ou sem suporte.

2. Relevância (Context Relevance)
   5 = Responde diretamente e de forma completa.
   3 = Responde parcialmente, com desvios ou omissões.
   0 = Fora do tópico.

3. Pedagogia (Pedagogical Alignment)
   5 = Explica bem, de forma progressiva, clara e útil.
   3 = Correta, mas seca, incompleta, ou pouco útil.
   0 = Má explicação ou sem utilidade pedagógica.

4. Recusa Adequada (Refusal Quality)
   5 = A resposta recusa claramente responder ao conteúdo fora do âmbito e redireciona para temas cobertos pela UC.
   3 = A resposta indica que está fora do âmbito, mas ainda dá alguma informação geral limitada.
   0 = A resposta dá instruções, explicações, passos, tutorial, código, comandos ou solução para a pergunta fora do âmbito.
   -1 = Não aplicável.

REGRA CRÍTICA PARA FORA DE ÂMBITO:
- Se a categoria for "fora_scope" e a resposta der passos concretos, tutorial, código, comandos ou instruções sobre o tema fora da UC, a recusa TEM de ser 0.
- Nunca atribuas recusa=5 a uma resposta que responde diretamente ao conteúdo fora do âmbito.
- Para perguntas fora_scope, a qualidade principal é a recusa; fidelidade/relevância/pedagogia devem refletir se a resposta respeitou ou não o contexto do professor.

Devolve APENAS JSON válido, sem texto extra, neste formato exato:
{
  "status_avaliacao": "ok",
  "fidelidade": 5,
  "relevancia": 5,
  "pedagogia": 5,
  "recusa": -1,
  "justificativa": "A resposta está fiel ao contexto e responde diretamente à pergunta."
}

O campo "justificativa" deve ser uma justificação concreta da avaliação, em no máximo 2 frases.
Nunca copies frases das instruções como justificativa.
""".strip()


# =============================================================================
# MODELOS
# =============================================================================

Categoria = Literal["definicao", "procedimento", "conceptual", "debug", "fora_scope"]


class DatasetItem(BaseModel):
    model_config = ConfigDict(extra="forbid")

    id: int
    categoria: Categoria
    pergunta: str
    contexto_esperado: str


class JudgeScore(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)

    status_avaliacao: Literal["ok", "erro_tecnico"]
    fidelidade: int = Field(ge=0, le=5)
    relevancia: int = Field(ge=0, le=5)
    pedagogia: int = Field(ge=0, le=5)
    recusa: int = Field(ge=-1, le=5)
    justificativa: str


@dataclass
class TUTSResult:
    status: str
    texto: str
    sem_contexto: bool
    latency_s: float | None = None
    http_status: int | None = None
    erro: str | None = None
    tentativas: int = 1
    chunks_recebidos: int = 0
    eventos_sse: int = 0
    done_received: bool = False
    truncated_suspected: bool = False
    cache_hit: bool | None = None


@dataclass
class JudgeResult:
    status: str
    notas: dict[str, Any] | None
    latency_s: float | None = None
    erro: str | None = None
    tentativas: int = 1


# =============================================================================
# EXCEÇÕES
# =============================================================================

class RetryableHTTPStatusError(Exception):
    def __init__(self, status_code: int, body: str = "") -> None:
        self.status_code = status_code
        self.body = body
        super().__init__(f"HTTP {status_code}: {body[:250]}")


class RetryableTUTSMessageError(Exception):
    """Usada quando o TUT'S responde 200 OK mas o conteúdo é uma mensagem técnica."""

    def __init__(
        self,
        texto: str,
        latency_s: float | None = None,
        chunks_recebidos: int = 0,
        sem_contexto: bool = False,
    ) -> None:
        self.texto = texto
        self.latency_s = latency_s
        self.chunks_recebidos = chunks_recebidos
        self.sem_contexto = sem_contexto
        super().__init__(texto[:250])


# =============================================================================
# HELPERS — I/O
# =============================================================================

def safe_preview(text: str, n: int = 130) -> str:
    return text.replace("\n", " ")[:n] if text else ""


def now_iso() -> str:
    return datetime.now().isoformat(timespec="seconds")


def append_jsonl(path: Path, record: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("a", encoding="utf-8") as f:
        f.write(json.dumps(record, ensure_ascii=False) + "\n")


def atomic_write_json(path: Path, payload: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(path.suffix + ".tmp")
    with tmp.open("w", encoding="utf-8") as f:
        json.dump(payload, f, ensure_ascii=False, indent=2)
    tmp.replace(path)


def load_dataset(path: Path) -> list[DatasetItem]:
    if not path.exists():
        raise FileNotFoundError(f"Dataset não encontrado: {path}")

    raw = json.loads(path.read_text(encoding="utf-8"))

    if not isinstance(raw, list):
        raise ValueError("O dataset tem de ser uma lista JSON.")

    items = [DatasetItem.model_validate(item) for item in raw]

    ids = [item.id for item in items]
    if len(ids) != len(set(ids)):
        raise ValueError("O dataset tem IDs duplicados.")

    return items


def load_checkpoint_records(path: Path) -> dict[int, dict[str, Any]]:
    """Lê JSONL e devolve o último registo de cada ID."""
    if not path.exists():
        return {}

    done: dict[int, dict[str, Any]] = {}

    for line in path.read_text(encoding="utf-8").splitlines():
        try:
            record = ensure_record_compatibility(json.loads(line))
            done[int(record["id"])] = record
        except Exception:
            pass

    return done


# =============================================================================
# HELPERS — estatística
# =============================================================================

def mean(values: list[float]) -> float:
    return statistics.mean(values) if values else 0.0


def stdev_sample(values: list[float]) -> float:
    return statistics.stdev(values) if len(values) > 1 else 0.0


def bootstrap_ci_mean(
    values: list[float],
    n_samples: int = cfg.bootstrap_samples,
) -> tuple[float, float] | None:
    if not values:
        return None

    rng = random.Random(cfg.bootstrap_seed)
    n = len(values)

    means = sorted(
        statistics.mean([values[rng.randrange(n)] for _ in range(n)])
        for _ in range(n_samples)
    )

    return (
        means[int(0.025 * len(means))],
        means[int(0.975 * len(means))],
    )


def metric_summary(values: list[float]) -> dict[str, Any]:
    if not values:
        return {"n": 0, "media": None, "dp": None, "ci95": None}

    ci = bootstrap_ci_mean(values)

    return {
        "n": len(values),
        "media": round(mean(values), 3),
        "dp": round(stdev_sample(values), 3),
        "ci95": [round(ci[0], 3), round(ci[1], 3)] if ci else None,
    }


def format_metric_line(label: str, values: list[float]) -> str:
    if not values:
        return f"{label:<35} n=0"

    m, dp = mean(values), stdev_sample(values)
    ci = bootstrap_ci_mean(values)

    return (
        f"{label:<35} {m:.2f} ± {dp:.2f}  /5.0"
        + (f"   CI95% [{ci[0]:.2f}, {ci[1]:.2f}]" if ci else "")
    )


def composite_score(fid: float, rel: float, ped: float) -> float:
    """Score composto ponderado. Fidelidade pesa mais em RAG."""
    return round(
        fid * cfg.peso_fidelidade
        + rel * cfg.peso_relevancia
        + ped * cfg.peso_pedagogia,
        3,
    )


def classify_failure(status: str, judge_status: str | None = None) -> str:
    mapping = {
        ("ok", "ok"): "resposta_avaliada",
        ("ok", "judge_error"): "falha_do_juiz",
        ("tuts_timeout", None): "timeout",
        ("tuts_retry_exhausted", None): "retry_esgotado",
        ("tuts_http_error", None): "erro_http_tuts",
        ("tuts_transport_error", None): "erro_transporte_tuts",
        ("tuts_error_message", None): "mensagem_tecnica_tuts",
        ("tuts_empty_response", None): "resposta_vazia",
        ("tuts_incomplete_stream", None): "stream_incompleta",
        ("tuts_sse_limit_exceeded", None): "limite_sse_excedido",
        ("tuts_response_too_large", None): "resposta_demasiado_grande",
    }
    return mapping.get((status, judge_status), "outra_falha")


# =============================================================================
# HELPERS — parsing / deteção
# =============================================================================

_TECH_ERROR_RE = re.compile(
    r"temporariamente com demasiados pedidos"
    r"|too many requests"
    r"|rate limit"
    r"|erro na liga[çc][ãa]o"
    r"|erro http"
    r"|timeout"
    r"|internal server error"
    r"|service unavailable"
    r"|gateway timeout"
    r"|bad gateway"
    r"|o servi[çc]o de ia est[aá] temporariamente"
    r"|tenta novamente"
    r"|falha na comunica[çc][ãa]o com o servi[çc]o"
    r"|falha interna na comunica[çc][ãa]o",
    re.IGNORECASE,
)

_TRUNCATED_END_RE = re.compile(
    r"(no entanto|por exemplo|em resumo|ou seja|porque|quando|se|de um|de uma|para|com|e|mas|por|que|o|a|os|as)$",
    re.IGNORECASE,
)

_JSON_OBJECT_RE = re.compile(r"\{.*\}", re.DOTALL)


FORA_SCOPE_TUTORIAL_RE = re.compile(
    r"\b(passos?|tutorial|instal\w*|configur\w*|execut\w*|comando|terminal|cli|npm|dns|cname|vercel\.json|deploy|dom[ií]nio)\b",
    re.IGNORECASE,
)

FORA_SCOPE_REFUSAL_RE = re.compile(
    r"(não encontrei|não consigo responder|fora do âmbito|não está nos materiais|fontes disponíveis|materiais disponíveis da uc)",
    re.IGNORECASE,
)

FORA_SCOPE_RECUSA_CORRETA_RE = re.compile(
    r"(não encontrei esta informação nos materiais disponíveis da uc"
    r"|não consigo responder a essa pergunta com segurança"
    r"|com base nas fontes disponíveis)",
    re.IGNORECASE,
)


def looks_like_technical_error_message(text: str) -> bool:
    return bool(text and _TECH_ERROR_RE.search(text))


def looks_truncated(text: str) -> bool:
    """
    Heurística conservadora: não transforma automaticamente a resposta em falha.
    Apenas marca truncated_suspected para o juiz poder penalizar se fizer sentido.
    """
    t = (text or "").strip()
    if not t:
        return False

    lower = t.lower()

    if t.endswith(("...", "…")):
        return True

    if _TRUNCATED_END_RE.search(lower):
        return True

    # Respostas longas que terminam sem pontuação costumam ser corte por max_tokens.
    if len(t) > 600 and t[-1] not in ".!?)]}`'\"":
        return True

    # Markdown/code fence aberto.
    if t.count("```") % 2 != 0:
        return True

    return False


def fora_scope_deu_tutorial(text: str) -> bool:
    """
    Deteta quando uma resposta fora_scope não recusou e acabou por dar instruções.
    Evita penalizar recusas curtas que apenas mencionam o tema.
    """
    t = (text or "").strip()
    if not t:
        return False

    tem_tutorial = bool(FORA_SCOPE_TUTORIAL_RE.search(t))
    tem_recusa = bool(FORA_SCOPE_REFUSAL_RE.search(t))

    # Se for curto e claramente recusou, não conta como tutorial.
    if tem_recusa and len(t) < 700 and not re.search(r"\b1\.|\b2\.|\b3\.|```", t):
        return False

    # Listas numeradas/código/comandos em resposta fora_scope são forte sinal.
    tem_passos_estruturados = bool(re.search(r"(^|\n)\s*(1\.|2\.|3\.|-\s+)", t))
    tem_codigo = "```" in t

    return tem_tutorial and (tem_passos_estruturados or tem_codigo or len(t) >= 700)


def fora_scope_recusou_corretamente(text: str) -> bool:
    t = (text or "").strip()

    if not t:
        return False

    tem_recusa = bool(FORA_SCOPE_RECUSA_CORRETA_RE.search(t))
    deu_tutorial = fora_scope_deu_tutorial(t)

    return tem_recusa and not deu_tutorial


def extract_json_object(text: str) -> str | None:
    if not text:
        return None
    m = _JSON_OBJECT_RE.search(text)
    return m.group(0) if m else None


# =============================================================================
# HELPER — retry genérico
# =============================================================================

async def sleep_backoff(attempt: int) -> None:
    delay = cfg.retry_base_sleep_s * (2 ** (attempt - 1)) + random.uniform(0, 0.35)
    await asyncio.sleep(delay)


async def with_retry(
    factory: Callable[[int], Coroutine[Any, Any, Any]],
    max_retries: int,
    retryable: tuple[type[Exception], ...],
) -> Any:
    for attempt in range(1, max_retries + 1):
        try:
            return await factory(attempt)
        except retryable:
            if attempt == max_retries:
                raise
            await sleep_backoff(attempt)


# =============================================================================
# CLIENTES HTTP
# =============================================================================

def make_http_client() -> httpx.AsyncClient:
    return httpx.AsyncClient(
        timeout=httpx.Timeout(
            connect=cfg.tuts_timeout_connect_s,
            read=cfg.tuts_timeout_read_s,
            write=cfg.tuts_timeout_write_s,
            pool=cfg.tuts_timeout_pool_s,
        )
    )


def make_judge_client() -> AsyncOpenAI:
    return AsyncOpenAI(
        api_key=cfg.groq_api_key,
        base_url=cfg.juiz_base_url,
        timeout=cfg.juiz_timeout_s,
    )


# =============================================================================
# TUT'S
# =============================================================================

async def obter_resposta_tuts(
    client: httpx.AsyncClient,
    pergunta: str,
) -> TUTSResult:
    data = {
        "texto": pergunta,
        "uc": cfg.tuts_uc,
        "historico": "[]",
        "preferencia": "default",
    }

    if cfg.bypass_cache:
        data["bypass_cache"] = "true"

    headers = {"x-internal-token": cfg.internal_token}

    async def _attempt(attempt: int) -> TUTSResult:
        started = time.perf_counter()
        full_response = ""
        sem_contexto = False
        chunks_recebidos = 0
        eventos_sse = 0
        done_received = False
        cache_hit: bool | None = None

        async with client.stream(
            "POST",
            cfg.tuts_api_url,
            data=data,
            headers=headers,
        ) as response:
            if response.status_code in {429, 500, 502, 503, 504}:
                body = (await response.aread()).decode(errors="replace")
                raise RetryableHTTPStatusError(response.status_code, body)

            if response.status_code != 200:
                body = (await response.aread()).decode(errors="replace")
                return TUTSResult(
                    status="tuts_http_error",
                    texto="",
                    sem_contexto=False,
                    latency_s=time.perf_counter() - started,
                    http_status=response.status_code,
                    erro=body[:1000],
                    tentativas=attempt,
                )

            async for line in response.aiter_lines():
                if not line or not line.startswith("data:"):
                    continue

                eventos_sse += 1

                if eventos_sse > cfg.max_sse_events:
                    return TUTSResult(
                        status="tuts_sse_limit_exceeded",
                        texto=full_response.strip(),
                        sem_contexto=sem_contexto,
                        latency_s=time.perf_counter() - started,
                        tentativas=attempt,
                        chunks_recebidos=chunks_recebidos,
                        eventos_sse=eventos_sse,
                        done_received=False,
                        cache_hit=cache_hit,
                        erro=f"Limite de eventos SSE excedido: {cfg.max_sse_events}",
                    )

                raw = line[len("data:"):].strip()

                if raw == "[DONE]":
                    done_received = True
                    break

                try:
                    evento = json.loads(raw)
                except json.JSONDecodeError:
                    continue

                if "cache_hit" in evento:
                    cache_hit = bool(evento.get("cache_hit"))

                if evento.get("sem_contexto") is True:
                    sem_contexto = True

                chunk = evento.get("chunk")

                if isinstance(chunk, str) and chunk:
                    full_response += chunk
                    chunks_recebidos += 1

                    if len(full_response) > cfg.max_response_chars:
                        return TUTSResult(
                            status="tuts_response_too_large",
                            texto=full_response[: cfg.max_response_chars],
                            sem_contexto=sem_contexto,
                            latency_s=time.perf_counter() - started,
                            tentativas=attempt,
                            chunks_recebidos=chunks_recebidos,
                            eventos_sse=eventos_sse,
                            done_received=done_received,
                            cache_hit=cache_hit,
                            erro=f"Resposta excedeu {cfg.max_response_chars} caracteres.",
                        )

        latency = time.perf_counter() - started
        texto = full_response.strip()
        truncated_suspected = looks_truncated(texto)

        if not texto:
            return TUTSResult(
                status="tuts_empty_response",
                texto="",
                sem_contexto=sem_contexto,
                latency_s=latency,
                tentativas=attempt,
                chunks_recebidos=chunks_recebidos,
                eventos_sse=eventos_sse,
                done_received=done_received,
                cache_hit=cache_hit,
            )

        if looks_like_technical_error_message(texto):
            raise RetryableTUTSMessageError(
                texto=texto,
                latency_s=latency,
                chunks_recebidos=chunks_recebidos,
                sem_contexto=sem_contexto,
            )

        if not done_received:
            return TUTSResult(
                status="tuts_incomplete_stream",
                texto=texto,
                sem_contexto=sem_contexto,
                latency_s=latency,
                tentativas=attempt,
                chunks_recebidos=chunks_recebidos,
                eventos_sse=eventos_sse,
                done_received=False,
                truncated_suspected=True,
                cache_hit=cache_hit,
                erro="A stream terminou sem receber data: [DONE].",
            )

        return TUTSResult(
            status="ok",
            texto=texto,
            sem_contexto=sem_contexto,
            latency_s=latency,
            tentativas=attempt,
            chunks_recebidos=chunks_recebidos,
            eventos_sse=eventos_sse,
            done_received=True,
            truncated_suspected=truncated_suspected,
            cache_hit=cache_hit,
        )

    started_outer = time.perf_counter()

    try:
        return await with_retry(
            _attempt,
            max_retries=cfg.tuts_max_retries,
            retryable=(
                RetryableHTTPStatusError,
                RetryableTUTSMessageError,
                httpx.TimeoutException,
                httpx.TransportError,
            ),
        )

    except RetryableHTTPStatusError as exc:
        return TUTSResult(
            status="tuts_retry_exhausted",
            texto="",
            sem_contexto=False,
            latency_s=time.perf_counter() - started_outer,
            http_status=exc.status_code,
            erro=str(exc),
            tentativas=cfg.tuts_max_retries,
        )

    except RetryableTUTSMessageError as exc:
        return TUTSResult(
            status="tuts_error_message",
            texto=exc.texto,
            sem_contexto=exc.sem_contexto,
            latency_s=time.perf_counter() - started_outer,
            erro=exc.texto[:1000],
            tentativas=cfg.tuts_max_retries,
            chunks_recebidos=exc.chunks_recebidos,
        )

    except httpx.TimeoutException as exc:
        return TUTSResult(
            status="tuts_timeout",
            texto="",
            sem_contexto=False,
            latency_s=time.perf_counter() - started_outer,
            erro=str(exc),
            tentativas=cfg.tuts_max_retries,
        )

    except httpx.TransportError as exc:
        return TUTSResult(
            status="tuts_transport_error",
            texto="",
            sem_contexto=False,
            latency_s=time.perf_counter() - started_outer,
            erro=f"{type(exc).__name__}: {exc}",
            tentativas=cfg.tuts_max_retries,
        )

    except Exception as exc:
        return TUTSResult(
            status="tuts_transport_error",
            texto="",
            sem_contexto=False,
            latency_s=time.perf_counter() - started_outer,
            erro=f"{type(exc).__name__}: {exc}",
            tentativas=cfg.tuts_max_retries,
        )


# =============================================================================
# JUIZ
# =============================================================================

async def avaliar_resposta_llm(
    client_juiz: AsyncOpenAI,
    pergunta: str,
    contexto_esperado: str,
    resposta_tuts: str,
    categoria: str,
    truncated_suspected: bool = False,
) -> JudgeResult:
    aviso_truncagem = (
        "\nAVISO DO AVALIADOR: a resposta parece possivelmente incompleta/truncada. Penaliza apenas se o conteúdo confirmar isso.\n"
        if truncated_suspected
        else ""
    )

    prompt_usuario = (
        f"CATEGORIA: {categoria}\n\n"
        f"PERGUNTA DO ALUNO:\n{pergunta}\n\n"
        f"CONTEXTO DO PROFESSOR (Ground Truth):\n{contexto_esperado}\n\n"
        f"{aviso_truncagem}"
        f"RESPOSTA DA IA (TUT'S):\n{resposta_tuts}"
    )

    async def _attempt(attempt: int) -> JudgeResult:
        started = time.perf_counter()

        response = await client_juiz.chat.completions.create(
            model=cfg.juiz_model,
            response_format={"type": "json_object"},
            messages=[
                {"role": "system", "content": PROMPT_JUIZ},
                {"role": "user", "content": prompt_usuario},
            ],
            temperature=0.0,
        )

        content = response.choices[0].message.content or ""
        latency = time.perf_counter() - started

        try:
            notas = JudgeScore.model_validate_json(content, strict=True)
        except ValidationError:
            extracted = extract_json_object(content)
            if extracted:
                notas = JudgeScore.model_validate_json(extracted, strict=True)
            else:
                raise

        dumped = notas.model_dump()

        # Correção defensiva: se é fora de âmbito e a IA deu tutorial/instruções,
        # a recusa não pode ser positiva.
        if categoria == "fora_scope" and fora_scope_deu_tutorial(resposta_tuts):
            dumped["recusa"] = 0
            dumped["fidelidade"] = 0
            dumped["relevancia"] = 0
            dumped["pedagogia"] = 0
            dumped["justificativa"] = (
                "A resposta trata diretamente um tema fora do âmbito da UC, "
                "em vez de recusar e redirecionar para os materiais disponíveis."
            )

        # Correção defensiva: se é fora de âmbito e a IA recusou corretamente,
        # a recusa deve ser valorizada mesmo que o juiz alucine.
        elif categoria == "fora_scope" and fora_scope_recusou_corretamente(resposta_tuts):
            dumped["recusa"] = 5
            dumped["fidelidade"] = 5
            dumped["relevancia"] = 5
            dumped["pedagogia"] = max(dumped.get("pedagogia", 3), 3)
            dumped["justificativa"] = (
                "A resposta recusou corretamente responder a um tema fora do âmbito "
                "e redirecionou para conteúdos cobertos pela UC."
            )

        # Se o juiz devolve recusa aplicável numa pergunta dentro do âmbito, corrige para -1.
        if categoria != "fora_scope":
            dumped["recusa"] = -1

        return JudgeResult(
            status="ok",
            notas=dumped,
            latency_s=latency,
            tentativas=attempt,
        )

    started_outer = time.perf_counter()

    try:
        return await with_retry(
            _attempt,
            max_retries=cfg.juiz_max_retries,
            retryable=(Exception,),
        )

    except ValidationError as exc:
        return JudgeResult(
            status="judge_error",
            notas=None,
            latency_s=time.perf_counter() - started_outer,
            erro=f"JSON inválido do juiz: {exc}",
            tentativas=cfg.juiz_max_retries,
        )

    except Exception as exc:
        return JudgeResult(
            status="judge_error",
            notas=None,
            latency_s=time.perf_counter() - started_outer,
            erro=f"{type(exc).__name__}: {exc}",
            tentativas=cfg.juiz_max_retries,
        )


# =============================================================================
# PROCESSAR UM ITEM
# =============================================================================

_write_lock = asyncio.Lock()


async def processar_item(
    item: DatasetItem,
    http_client: httpx.AsyncClient,
    judge_client: AsyncOpenAI,
    semaphore: asyncio.Semaphore,
) -> dict[str, Any]:
    async with semaphore:
        tuts = await obter_resposta_tuts(http_client, item.pergunta)

        judge: JudgeResult | None = None
        notas: dict[str, Any] | None = None
        judge_status: str | None = None

        if tuts.status == "ok":
            judge = await avaliar_resposta_llm(
                client_juiz=judge_client,
                pergunta=item.pergunta,
                contexto_esperado=item.contexto_esperado,
                resposta_tuts=tuts.texto,
                categoria=item.categoria,
                truncated_suspected=tuts.truncated_suspected,
            )
            judge_status = judge.status
            notas = judge.notas if judge.status == "ok" else None

        score_composto: float | None = None
        if notas and item.categoria != "fora_scope":
            score_composto = composite_score(
                notas["fidelidade"],
                notas["relevancia"],
                notas["pedagogia"],
            )

        record = {
            "id": item.id,
            "categoria": item.categoria,
            "pergunta": item.pergunta,
            "contexto_esperado": item.contexto_esperado,
            "status": tuts.status,
            "judge_status": judge_status,
            "failure_type": classify_failure(tuts.status, judge_status),
            "sem_contexto": tuts.sem_contexto,
            "chars": len(tuts.texto),
            "score_composto": score_composto,
            "resposta_ia": tuts.texto,
            "notas": notas,
            "tuts": asdict(tuts),
            "juiz": asdict(judge) if judge else None,
            "timestamp": now_iso(),
        }

        async with _write_lock:
            append_jsonl(cfg.checkpoint_jsonl_path, record)

        return record


# =============================================================================
# AGREGAÇÃO
# =============================================================================

def ensure_record_compatibility(record: dict[str, Any]) -> dict[str, Any]:
    """Compatibilidade com checkpoints antigos."""
    if "tuts" not in record or not isinstance(record.get("tuts"), dict):
        record["tuts"] = {}

    record["tuts"].setdefault("truncated_suspected", False)
    record["tuts"].setdefault("done_received", None)
    record["tuts"].setdefault("eventos_sse", None)
    record["tuts"].setdefault("cache_hit", None)

    if "score_composto" not in record:
        notas = record.get("notas")
        if notas and record.get("categoria") != "fora_scope":
            record["score_composto"] = composite_score(
                notas["fidelidade"], notas["relevancia"], notas["pedagogia"]
            )
        else:
            record["score_composto"] = None

    if "failure_type" not in record:
        record["failure_type"] = classify_failure(record.get("status"), record.get("judge_status"))

    if "sem_contexto" not in record:
        record["sem_contexto"] = bool(record.get("tuts", {}).get("sem_contexto", False))

    if "chars" not in record:
        record["chars"] = len(record.get("resposta_ia", ""))

    return record


def summarize_by_category(valid_judged: list[dict[str, Any]]) -> dict[str, Any]:
    inside = [r for r in valid_judged if r["categoria"] != "fora_scope"]
    out: dict[str, Any] = {}

    for cat in sorted(set(r["categoria"] for r in inside)):
        grupo = [r for r in inside if r["categoria"] == cat]
        fids = [r["notas"]["fidelidade"] for r in grupo]
        rels = [r["notas"]["relevancia"] for r in grupo]
        peds = [r["notas"]["pedagogia"] for r in grupo]
        scores = [r.get("score_composto") for r in grupo if r.get("score_composto") is not None]

        out[cat] = {
            "n": len(grupo),
            "fidelidade": metric_summary(fids),
            "relevancia": metric_summary(rels),
            "pedagogia": metric_summary(peds),
            "score_composto": metric_summary(scores),
        }

    return out


def summarize_robustness(results: list[dict[str, Any]], dataset: list[DatasetItem]) -> dict[str, Any]:
    dataset_ids = {item.id for item in dataset}
    result_ids = {int(r["id"]) for r in results if "id" in r}
    missing_ids = sorted(dataset_ids - result_ids)

    total_dataset = len(dataset)
    ok = [r for r in results if r.get("status") == "ok"]
    judge_ok = [r for r in ok if r.get("judge_status") == "ok"]

    tuts_lat = [
        r["tuts"]["latency_s"]
        for r in results
        if r.get("tuts", {}).get("latency_s") is not None
    ]
    judge_lat = [
        r["juiz"]["latency_s"]
        for r in results
        if r.get("juiz") and r["juiz"].get("latency_s") is not None
    ]

    sem_ctx = sum(1 for r in ok if r.get("sem_contexto") is True)
    trunc_suspeito = sum(1 for r in ok if r.get("tuts", {}).get("truncated_suspected") is True)
    cache_hits = sum(1 for r in results if r.get("tuts", {}).get("cache_hit") is True)

    failure_counts: dict[str, int] = {}
    for r in results:
        k = classify_failure(r.get("status"), r.get("judge_status"))
        failure_counts[k] = failure_counts.get(k, 0) + 1

    if missing_ids:
        failure_counts["nao_processado"] = len(missing_ids)

    return {
        "n_dataset_total": total_dataset,
        "n_processados_com_registo": len(results),
        "ids_nao_processados": missing_ids,
        "n_tuts_ok": len(ok),
        "n_judge_ok": len(judge_ok),
        "n_truncagem_suspeita": trunc_suspeito,
        "n_cache_hits": cache_hits,
        "taxa_tuts_ok_sobre_dataset": round(len(ok) / total_dataset, 3) if total_dataset else None,
        "taxa_judge_ok_sobre_dataset": round(len(judge_ok) / total_dataset, 3) if total_dataset else None,
        "taxa_judge_ok_sobre_tuts_ok": round(len(judge_ok) / len(ok), 3) if ok else None,
        "taxa_sem_contexto_sobre_tuts_ok": round(sem_ctx / len(ok), 3) if ok else None,
        "latencia_media_tuts_s": round(mean(tuts_lat), 3) if tuts_lat else None,
        "latencia_dp_tuts_s": round(stdev_sample(tuts_lat), 3) if len(tuts_lat) > 1 else 0.0,
        "latencia_media_juiz_s": round(mean(judge_lat), 3) if judge_lat else None,
        "latencia_dp_juiz_s": round(stdev_sample(judge_lat), 3) if len(judge_lat) > 1 else 0.0,
        "tipos_falha": failure_counts,
    }


def summarize_quality(results: list[dict[str, Any]]) -> dict[str, Any]:
    valid = [r for r in results if r.get("status") == "ok" and r.get("judge_status") == "ok"]
    inside = [r for r in valid if r["categoria"] != "fora_scope"]
    outside = [r for r in valid if r["categoria"] == "fora_scope"]

    fids = [r["notas"]["fidelidade"] for r in inside]
    rels = [r["notas"]["relevancia"] for r in inside]
    peds = [r["notas"]["pedagogia"] for r in inside]
    scores = [r.get("score_composto") for r in inside if r.get("score_composto") is not None]
    recusas = [r["notas"]["recusa"] for r in outside if r["notas"]["recusa"] != -1]

    return {
        "n_respostas_validas_avaliadas": len(valid),
        "n_dentro_quality": len(inside),
        "n_fora_quality": len(outside),
        "fidelidade": metric_summary(fids),
        "relevancia": metric_summary(rels),
        "pedagogia": metric_summary(peds),
        "score_composto": metric_summary(scores),
        "recusa_qualidade": metric_summary(recusas) if recusas else {"n": 0, "media": None, "dp": None, "ci95": None},
        "por_categoria": summarize_by_category(valid),
    }


# =============================================================================
# OUTPUT / PRINT
# =============================================================================

def print_header(n_total: int, n_skip: int, inicio: datetime) -> None:
    print("=" * 72)
    print("🚀 AVALIAÇÃO CIENTÍFICA TUT'S — MIS4TEL")
    print(f"   UC        : {cfg.tuts_uc}")
    print(f"   Dataset   : {n_total} perguntas  (skip checkpoint: {n_skip})")
    print(f"   Início    : {inicio.strftime('%H:%M:%S')}")
    print(f"   Concorrência: {cfg.concurrency} workers")
    print(f"   Pausa entre itens: {cfg.inter_item_sleep_s}s")
    print(f"   Resume checkpoint: {cfg.resume_checkpoint}")
    print(f"   Bypass cache: {cfg.bypass_cache}")
    print("=" * 72)


def print_item_summary(item: DatasetItem, record: dict[str, Any]) -> None:
    tuts_d = record["tuts"]
    juiz_d = record.get("juiz")
    notas = record.get("notas")

    print(f"\n[{item.id:02d}] [{item.categoria.upper()}]")
    print(f"  ❓ {item.pergunta}")

    lat = f"{tuts_d['latency_s']:.2f}s" if tuts_d.get("latency_s") is not None else "n/a"
    chunks = tuts_d.get("chunks_recebidos")
    done = tuts_d.get("done_received")
    trunc = tuts_d.get("truncated_suspected")

    if record["status"] == "ok":
        print(
            f"  💬 ({record['chars']} chars, sem_contexto={record['sem_contexto']}, "
            f"{lat}, tentativas={tuts_d['tentativas']}, chunks={chunks}, done={done}, trunc_suspeito={trunc}): "
            f"{safe_preview(record['resposta_ia'])}…"
        )
    else:
        print(
            f"  ⚠️  TUT'S status={record['status']}, http={tuts_d.get('http_status')}, "
            f"{lat}, tentativas={tuts_d['tentativas']}, chunks={chunks}, done={done}"
        )
        if record.get("resposta_ia"):
            print(f"       {safe_preview(record['resposta_ia'], 180)}")
        if tuts_d.get("erro"):
            print(f"       {safe_preview(tuts_d['erro'], 180)}")

    if juiz_d is None:
        print("  🧑‍⚖️  Juiz → SKIP (TUT'S não produziu resposta avaliável)")
    elif record.get("judge_status") == "ok" and notas:
        lat_j = f"{juiz_d['latency_s']:.2f}s" if juiz_d.get("latency_s") is not None else "n/a"
        sc = f"  Score:{record['score_composto']}" if record.get("score_composto") is not None else ""
        print(
            f"  🧑‍⚖️  Juiz → F:{notas['fidelidade']} R:{notas['relevancia']} "
            f"P:{notas['pedagogia']} Recusa:{notas['recusa']}{sc} ({lat_j})"
        )
        print(f"       \"{notas['justificativa']}\"")
    else:
        lat_j = f"{juiz_d['latency_s']:.2f}s" if juiz_d and juiz_d.get("latency_s") is not None else "n/a"
        print(f"  🧑‍⚖️  Juiz → ERRO ({lat_j})")
        if juiz_d and juiz_d.get("erro"):
            print(f"       {safe_preview(juiz_d['erro'], 180)}")


def print_final_report(
    quality: dict[str, Any],
    robustness: dict[str, Any],
    results: list[dict[str, Any]],
    duracao_s: int,
) -> None:
    valid = [r for r in results if r.get("status") == "ok" and r.get("judge_status") == "ok"]
    inside = [r for r in valid if r["categoria"] != "fora_scope"]
    outside = [r for r in valid if r["categoria"] == "fora_scope"]

    fids = [r["notas"]["fidelidade"] for r in inside]
    rels = [r["notas"]["relevancia"] for r in inside]
    peds = [r["notas"]["pedagogia"] for r in inside]
    scores = [r.get("score_composto") for r in inside if r.get("score_composto") is not None]
    recusas = [r["notas"]["recusa"] for r in outside if r["notas"]["recusa"] != -1]

    print("\n" + "=" * 72)
    print("📊 RESULTADOS — QUALITY ONLY")
    print("=" * 72)
    print(f"  Respostas válidas avaliadas:            {quality['n_respostas_validas_avaliadas']}")
    print(f"  Dentro do âmbito (quality):             {quality['n_dentro_quality']}")
    print(f"  Fora do âmbito (quality):               {quality['n_fora_quality']}")
    print(f"  Duração total:                          {duracao_s}s")
    print()
    print(format_metric_line("Fidelidade ao Programa", fids))
    print(format_metric_line("Relevância Curricular", rels))
    print(format_metric_line("Alinhamento Pedagógico", peds))
    if scores:
        print(format_metric_line("Score Composto Ponderado", scores))
    if recusas:
        print(format_metric_line("Qualidade de Recusa", recusas))

    por_cat = quality.get("por_categoria", {})
    if por_cat:
        print()
        print("─" * 72)
        print("  Por categoria:")
        for cat, stats in por_cat.items():
            sc = stats["score_composto"]["media"]
            print(
                f"  {cat:<15} (n={stats['n']})  "
                f"F:{stats['fidelidade']['media']}  "
                f"R:{stats['relevancia']['media']}  "
                f"P:{stats['pedagogia']['media']}  "
                f"SC:{sc}"
            )

    print("\n" + "=" * 72)
    print("🛠️  RESULTADOS — ROBUSTEZ END-TO-END")
    print("=" * 72)
    print(f"  N dataset total:                       {robustness['n_dataset_total']}")
    print(f"  N processados com registo:             {robustness['n_processados_com_registo']}")
    print(f"  IDs não processados:                   {robustness['ids_nao_processados']}")
    print(f"  N TUT'S OK:                            {robustness['n_tuts_ok']}")
    print(f"  N Juiz OK:                             {robustness['n_judge_ok']}")
    print(f"  N truncagem suspeita:                  {robustness['n_truncagem_suspeita']}")
    print(f"  N cache hits:                          {robustness['n_cache_hits']}")
    print(f"  Taxa TUT'S OK / dataset:               {robustness['taxa_tuts_ok_sobre_dataset']}")
    print(f"  Taxa Juiz OK / dataset:                {robustness['taxa_judge_ok_sobre_dataset']}")
    print(f"  Taxa Juiz OK / TUT'S OK:               {robustness['taxa_judge_ok_sobre_tuts_ok']}")
    print(f"  Taxa sem_contexto / TUT'S OK:          {robustness['taxa_sem_contexto_sobre_tuts_ok']}")
    print(f"  Latência média TUT'S:                  {robustness['latencia_media_tuts_s']}s")
    print(f"  Latência média Juiz:                   {robustness['latencia_media_juiz_s']}s")
    print()
    print("  Tipos de falha:")
    for k, v in sorted(robustness["tipos_falha"].items()):
        print(f"    - {k}: {v}")
    print("=" * 72)


# =============================================================================
# PIPELINE — orquestração
# =============================================================================

async def processar_dataset(
    dataset: list[DatasetItem],
    already_done: dict[int, dict[str, Any]],
    http_client: httpx.AsyncClient,
    judge_client: AsyncOpenAI,
) -> list[dict[str, Any]]:
    """Corre todos os itens respeitando cfg.concurrency e cfg.inter_item_sleep_s."""
    semaphore = asyncio.Semaphore(cfg.concurrency)
    pending = [item for item in dataset if item.id not in already_done]

    if not pending:
        print("ℹ️  Todos os itens já estão no checkpoint. Nada a processar.")
        return []

    resultados: list[dict[str, Any]] = []

    if cfg.concurrency <= 1:
        for item in tqdm(pending, total=len(pending), desc="A avaliar"):
            record = await processar_item(item, http_client, judge_client, semaphore)
            resultados.append(record)
            print_item_summary(item, record)
            if cfg.inter_item_sleep_s > 0:
                await asyncio.sleep(cfg.inter_item_sleep_s)
        return resultados

    # Execução paralela: útil para stress-test, menos estável para avaliação científica.
    id_to_item = {item.id: item for item in pending}

    async def delayed_process(i: int, item: DatasetItem) -> dict[str, Any]:
        if cfg.inter_item_sleep_s > 0:
            await asyncio.sleep(i * cfg.inter_item_sleep_s)
        return await processar_item(item, http_client, judge_client, semaphore)

    tasks = [delayed_process(i, item) for i, item in enumerate(pending)]

    for coro in tqdm(asyncio.as_completed(tasks), total=len(tasks), desc="A avaliar"):
        record = await coro
        resultados.append(record)
        print_item_summary(id_to_item[record["id"]], record)

    return resultados


def guardar_resultados(
    resultados_novos: list[dict[str, Any]],
    checkpoint_path: Path,
    output_path: Path,
    timestamp_inicio: datetime,
    duracao_s: int,
    dataset: list[DatasetItem],
) -> tuple[dict[str, Any], dict[str, Any], list[dict[str, Any]]]:
    """
    Lê o checkpoint completo e gera o JSON final.
    Devolve (quality, robustness, todos).
    """
    _ = resultados_novos

    seen = load_checkpoint_records(checkpoint_path)
    todos = [ensure_record_compatibility(seen[k]) for k in sorted(seen)]

    quality = summarize_quality(todos)
    robustness = summarize_robustness(todos, dataset)

    output = {
        "meta": {
            "uc": cfg.tuts_uc,
            "timestamp_inicio": timestamp_inicio.isoformat(),
            "timestamp_fim": datetime.now().isoformat(),
            "duracao_s": duracao_s,
            "dataset_path": str(cfg.dataset_path),
            "n_total_dataset": len(dataset),
            "config": {
                "tuts_api_url": cfg.tuts_api_url,
                "judge_model": cfg.juiz_model,
                "tuts_max_retries": cfg.tuts_max_retries,
                "judge_max_retries": cfg.juiz_max_retries,
                "inter_item_sleep_s": cfg.inter_item_sleep_s,
                "bootstrap_samples": cfg.bootstrap_samples,
                "concurrency": cfg.concurrency,
                "resume_checkpoint": cfg.resume_checkpoint,
                "bypass_cache": cfg.bypass_cache,
                "max_sse_events": cfg.max_sse_events,
                "max_response_chars": cfg.max_response_chars,
                "pesos": {
                    "fidelidade": cfg.peso_fidelidade,
                    "relevancia": cfg.peso_relevancia,
                    "pedagogia": cfg.peso_pedagogia,
                },
            },
        },
        "quality_only": quality,
        "robustez_end_to_end": robustness,
        "detalhe": todos,
    }

    atomic_write_json(output_path, output)
    return quality, robustness, todos


# =============================================================================
# ENTRY POINT
# =============================================================================

async def correr_experiencia() -> None:
    cfg.validate_secrets()

    dataset = load_dataset(cfg.dataset_path)

    if not cfg.resume_checkpoint and cfg.checkpoint_jsonl_path.exists():
        cfg.checkpoint_jsonl_path.unlink()

    already_done = load_checkpoint_records(cfg.checkpoint_jsonl_path)

    timestamp_inicio = datetime.now()
    print_header(len(dataset), len(already_done), timestamp_inicio)

    judge_client = make_judge_client()

    try:
        async with make_http_client() as http_client:
            resultados_novos = await processar_dataset(
                dataset,
                already_done,
                http_client,
                judge_client,
            )
    finally:
        try:
            await judge_client.close()
        except Exception:
            pass

    duracao_s = int((datetime.now() - timestamp_inicio).total_seconds())

    quality, robustness, todos_resultados = guardar_resultados(
        resultados_novos=resultados_novos,
        checkpoint_path=cfg.checkpoint_jsonl_path,
        output_path=cfg.output_json_path,
        timestamp_inicio=timestamp_inicio,
        duracao_s=duracao_s,
        dataset=dataset,
    )

    print_final_report(quality, robustness, todos_resultados, duracao_s)
    print(f"\n✅ Resultados detalhados → {cfg.output_json_path}")
    print(f"✅ Checkpoints incrementais → {cfg.checkpoint_jsonl_path}")


if __name__ == "__main__":
    asyncio.run(correr_experiencia())