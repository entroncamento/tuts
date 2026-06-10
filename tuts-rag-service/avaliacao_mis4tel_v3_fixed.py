from __future__ import annotations

import argparse
import asyncio
import csv
import hashlib
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
from pydantic import BaseModel, ConfigDict, Field, ValidationError, field_validator, model_validator
from pydantic_settings import BaseSettings, SettingsConfigDict
from tqdm import tqdm


# =============================================================================
# TUT'S — MIS4TEL proof-of-concept evaluator v3
# -----------------------------------------------------------------------------
# Objetivo científico:
#   - avaliar viabilidade técnica e qualidade preliminar de respostas RAG;
#   - NÃO provar ganhos de aprendizagem nem eficácia pedagógica final;
#   - gerar artefactos auditáveis: JSON, JSONL, CSV para revisão humana e relatório MD.
# =============================================================================


# =============================================================================
# CONFIG
# =============================================================================

class Config(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
        populate_by_name=True,
        case_sensitive=False,
    )

    # Endpoint TUT'S
    tuts_api_url: str = "http://localhost:8001/perguntar"
    tuts_uc: str = "Tecnologias_Avancadas_para_Client-side"

    # Segredos
    internal_token: str = Field(alias="INTERNAL_TOKEN", default="")
    groq_api_key: str = Field(alias="GROQ_API_KEY", default="")

    # Juiz LLM
    juiz_base_url: str = "https://api.groq.com/openai/v1"
    juiz_model: str = "llama-3.1-8b-instant"

    # Ficheiros
    dataset_path: Path = Path("dataset_tacs_mis4tel_v3.json")
    output_json_path: Path = Path("resultados_mis4tel_v3.json")
    checkpoint_jsonl_path: Path = Path("resultados_mis4tel_v3.jsonl")
    human_review_csv_path: Path = Path("resultados_mis4tel_v3_human_review.csv")
    report_md_path: Path = Path("resultados_mis4tel_v3_report.md")

    # Checkpoint
    resume_checkpoint: bool = False

    # Cache do TUT'S
    bypass_cache: bool = True

    # Rede / retries
    tuts_max_retries: int = 4
    juiz_max_retries: int = 3
    retry_base_sleep_s: float = 1.25
    inter_item_sleep_s: float = 1.0
    concurrency: int = 1

    # Timeouts
    tuts_timeout_connect_s: float = 10.0
    tuts_timeout_read_s: float = 180.0
    tuts_timeout_write_s: float = 20.0
    tuts_timeout_pool_s: float = 10.0
    juiz_timeout_s: float = 90.0

    # Limites defensivos
    max_sse_events: int = 5000
    max_response_chars: int = 80_000

    # Estatística
    bootstrap_samples: int = 4000
    bootstrap_seed: int = 42
    min_n_for_ci: int = 20

    # Score composto
    peso_fidelidade: float = 0.40
    peso_relevancia: float = 0.35
    peso_pedagogia: float = 0.25

    # Revisão humana
    flag_perfect_scores_for_review: bool = True
    citation_expected_for_in_scope: bool = True

    @model_validator(mode="after")
    def validar_config(self) -> "Config":
        self.concurrency = max(1, int(self.concurrency))
        self.tuts_max_retries = max(1, int(self.tuts_max_retries))
        self.juiz_max_retries = max(1, int(self.juiz_max_retries))
        self.bootstrap_samples = max(500, int(self.bootstrap_samples))
        self.max_sse_events = max(100, int(self.max_sse_events))
        self.max_response_chars = max(5_000, int(self.max_response_chars))
        self.min_n_for_ci = max(2, int(self.min_n_for_ci))
        total_pesos = self.peso_fidelidade + self.peso_relevancia + self.peso_pedagogia
        if abs(total_pesos - 1.0) > 0.001:
            raise ValueError("Os pesos do score composto devem somar 1.0")
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
# PROMPT DO JUIZ — MAIS EXIGENTE E AUDITÁVEL
# =============================================================================

PROMPT_JUIZ = """
És um avaliador académico rigoroso em Tecnologia Educativa, RAG e aprendizagem no ensino superior.

Avalia a RESPOSTA DA IA face à PERGUNTA DO ALUNO, ao CONTEXTO DE REFERÊNCIA e aos critérios explícitos de avaliação.

ENQUADRAMENTO:
- Esta avaliação é preliminar e serve para testar viabilidade de um pipeline RAG.
- Não assumas que uma resposta bem escrita é automaticamente correta.
- Não dês 5 por defeito. Um 5 exige que a resposta cumpra todos ou quase todos os critérios essenciais, sem erros factuais relevantes.

REGRAS GERAIS:
- Avalia o conteúdo factual e pedagógico, não apenas a fluência textual.
- Penaliza exemplos de código incorretos, comentários errados no código, confusão entre conceitos ou afirmações não suportadas.
- Penaliza respostas que pareçam completas mas omitam critérios essenciais do contexto de referência.
- Pequenos acréscimos pedagógicos são aceitáveis se não contradisserem o contexto.
- Se a resposta estiver truncada, incompleta ou terminar abruptamente, penaliza relevância e pedagogia.
- Se a resposta for claramente erro técnico, timeout, overload, rate limit ou falha de serviço, devolve status_avaliacao="erro_tecnico".
- A métrica "recusa" aplica-se apenas a perguntas fora_scope. Para perguntas dentro do âmbito, usa recusa=-1.

CRITÉRIOS:
1. Fidelidade / Faithfulness (0-5)
   5 = conteúdo totalmente compatível com o contexto; sem erros factuais; sem alucinações relevantes.
   4 = muito fiel, mas com pequena imprecisão ou detalhe não suportado sem grande impacto.
   3 = parcialmente fiel; inclui acréscimos discutíveis, omissões ou imprecisões moderadas.
   2 = várias imprecisões ou suporte fraco no contexto.
   1 = maioritariamente não suportada.
   0 = inventada, contraditória ou tecnicamente errada.

2. Relevância / Context Relevance (0-5)
   5 = responde diretamente e cobre os pontos essenciais.
   4 = responde bem, mas omite um ponto secundário.
   3 = responde parcialmente ou com desvios.
   2 = responde pouco ao que foi perguntado.
   1 = quase fora do tópico.
   0 = fora do tópico.

3. Pedagogia / Pedagogical Alignment (0-5)
   5 = explicação clara, progressiva, útil para estudo autónomo, com exemplos corretos quando apropriado.
   4 = clara e útil, mas podia guiar melhor o estudante.
   3 = correta mas seca, genérica, incompleta ou pouco orientadora.
   2 = difícil de seguir ou pouco útil.
   1 = confusa.
   0 = pedagogicamente inútil ou enganadora.

4. Recusa adequada / Refusal Quality (-1, 0-5)
   5 = recusa claramente responder a conteúdo fora do âmbito e redireciona para temas cobertos pela UC.
   4 = recusa corretamente, com pequeno excesso de texto.
   3 = indica fora de âmbito mas ainda dá alguma informação geral limitada.
   2 = recusa ambígua.
   1 = quase não recusa.
   0 = dá passos, tutorial, código, comandos ou solução para tema fora do âmbito.
   -1 = não aplicável.

REGRA CRÍTICA PARA FORA DE ÂMBITO:
- Se categoria="fora_scope" e a resposta der passos concretos, tutorial, código, comandos ou solução, recusa=0.
- Para fora_scope, avalia sobretudo se recusou corretamente.

Devolve APENAS JSON válido, sem markdown e sem texto extra, com este formato:
{
  "status_avaliacao": "ok",
  "fidelidade": 0,
  "relevancia": 0,
  "pedagogia": 0,
  "recusa": -1,
  "criterios_cumpridos": ["..."],
  "criterios_em_falta": ["..."],
  "alegacoes_nao_suportadas": ["..."],
  "erros_factuais": ["..."],
  "justificativa": "Justificação concreta em no máximo 2 frases."
}
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

    # Campos v3 opcionais: tornam a avaliação mais auditável sem quebrar datasets antigos.
    must_include: list[str] = Field(default_factory=list)
    must_not_include: list[str] = Field(default_factory=list)
    expected_sources: list[str] = Field(default_factory=list)
    evaluation_notes: str = ""

    @field_validator("pergunta", "contexto_esperado")
    @classmethod
    def non_empty(cls, value: str) -> str:
        if not value or not value.strip():
            raise ValueError("Campo obrigatório vazio.")
        return value.strip()


class JudgeScore(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)

    status_avaliacao: Literal["ok", "erro_tecnico"]
    fidelidade: int = Field(ge=0, le=5)
    relevancia: int = Field(ge=0, le=5)
    pedagogia: int = Field(ge=0, le=5)
    recusa: int = Field(ge=-1, le=5)
    criterios_cumpridos: list[str] = Field(default_factory=list)
    criterios_em_falta: list[str] = Field(default_factory=list)
    alegacoes_nao_suportadas: list[str] = Field(default_factory=list)
    erros_factuais: list[str] = Field(default_factory=list)
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
# I/O
# =============================================================================

def safe_preview(text: str, n: int = 140) -> str:
    return re.sub(r"\s+", " ", text or "").strip()[:n]


def now_iso() -> str:
    return datetime.now().isoformat(timespec="seconds")


def stable_run_id(dataset: list[DatasetItem]) -> str:
    payload = json.dumps([item.model_dump() for item in dataset], ensure_ascii=False, sort_keys=True)
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()[:12]


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

    return sorted(items, key=lambda x: x.id)


def load_checkpoint_records(path: Path) -> dict[int, dict[str, Any]]:
    if not path.exists():
        return {}

    done: dict[int, dict[str, Any]] = {}
    for line in path.read_text(encoding="utf-8").splitlines():
        try:
            record = ensure_record_compatibility(json.loads(line))
            done[int(record["id"])] = record
        except Exception:
            continue
    return done


# =============================================================================
# ESTATÍSTICA
# =============================================================================

def mean(values: list[float]) -> float:
    return statistics.mean(values) if values else 0.0


def stdev_sample(values: list[float]) -> float:
    return statistics.stdev(values) if len(values) > 1 else 0.0


def bootstrap_ci_mean(values: list[float], n_samples: int = cfg.bootstrap_samples) -> tuple[float, float] | None:
    if not values or len(values) < cfg.min_n_for_ci:
        return None
    rng = random.Random(cfg.bootstrap_seed)
    n = len(values)
    means = sorted(statistics.mean([values[rng.randrange(n)] for _ in range(n)]) for _ in range(n_samples))
    return means[int(0.025 * len(means))], means[int(0.975 * len(means))]


def metric_summary(values: list[float]) -> dict[str, Any]:
    if not values:
        return {"n": 0, "media": None, "dp": None, "ci95": None, "ci95_note": None}
    ci = bootstrap_ci_mean(values)
    return {
        "n": len(values),
        "media": round(mean(values), 3),
        "dp": round(stdev_sample(values), 3),
        "ci95": [round(ci[0], 3), round(ci[1], 3)] if ci else None,
        "ci95_note": None if ci else f"CI95 omitido: n < {cfg.min_n_for_ci}; interpretar como avaliação exploratória.",
    }


def composite_score(fid: float, rel: float, ped: float) -> float:
    return round(fid * cfg.peso_fidelidade + rel * cfg.peso_relevancia + ped * cfg.peso_pedagogia, 3)


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
# DETEÇÃO AUTOMÁTICA / SANITY CHECKS
# =============================================================================

_TECH_ERROR_RE = re.compile(
    r"temporariamente com demasiados pedidos|too many requests|rate limit|erro na liga[çc][ãa]o|erro http|timeout|internal server error|service unavailable|gateway timeout|bad gateway|o servi[çc]o de ia est[aá] temporariamente|tenta novamente|falha na comunica[çc][ãa]o com o servi[çc]o|falha interna na comunica[çc][ãa]o",
    re.IGNORECASE,
)

_TRUNCATED_END_RE = re.compile(
    r"(no entanto|por exemplo|em resumo|ou seja|porque|quando|se|de um|de uma|para|com|e|mas|por|que|o|a|os|as)$",
    re.IGNORECASE,
)

_JSON_OBJECT_RE = re.compile(r"\{.*\}", re.DOTALL)
# Aceita os formatos que o TUT'S tem usado, por exemplo:
# [Tecnologias_Avancadas_para_Client-side_TACS_Sebenta_2023_merged.pdf:10]
# [Tecnologias_Avancadas_para_Client-side_TACS_Sebenta_2023_merged.pdf:p. 10]
# [Ficheiro:Página:10]
_CITATION_RE = re.compile(
    r"\[[^\]\n]{1,220}:\s*(?:(?:p\.?|pp\.?|pag\.?|página|page)\s*[:.]?\s*)?\d+(?:\s*[-–]\s*\d+)?\]",
    re.IGNORECASE,
)

FORA_SCOPE_TUTORIAL_RE = re.compile(
    r"\b(passos?|tutorial|instal\w*|configur\w*|execut\w*|comando|terminal|cli|npm|dns|cname|vercel\.json|deploy|dom[ií]nio|netlify|aws|hosting)\b",
    re.IGNORECASE,
)
FORA_SCOPE_REFUSAL_RE = re.compile(
    r"(não encontrei|não consigo responder|fora do âmbito|não está nos materiais|fontes disponíveis|materiais disponíveis da uc|não está coberto)",
    re.IGNORECASE,
)
FORA_SCOPE_RECUSA_CORRETA_RE = re.compile(
    r"(não encontrei esta informação nos materiais disponíveis da uc|não consigo responder a essa pergunta com segurança|com base nas fontes disponíveis|não está coberto pelos materiais)",
    re.IGNORECASE,
)


def normalize_text(text: str) -> str:
    text = (text or "").lower()
    # Normalização simples sem dependências externas.
    replacements = {
        "á": "a", "à": "a", "ã": "a", "â": "a",
        "é": "e", "ê": "e", "í": "i", "ó": "o", "õ": "o", "ô": "o", "ú": "u", "ç": "c",
    }
    for src, dst in replacements.items():
        text = text.replace(src, dst)
    return re.sub(r"\s+", " ", text).strip()


def _tokens_informativos(text: str, min_len: int = 3) -> list[str]:
    return [
        t
        for t in re.findall(r"[a-zA-Z0-9_]+", normalize_text(text))
        if len(t) >= min_len
    ]


def contains_loose(haystack: str, needle: str) -> bool:
    """Deteção permissiva para critérios que DEVEM estar presentes.

    Esta função é intencionalmente flexível porque os critérios must_include
    podem aparecer parafraseados. Não deve ser usada para must_not_include,
    porque aí a flexibilidade gera falsos positivos.
    """
    h = normalize_text(haystack)
    n = normalize_text(needle)

    if not n:
        return True

    if n in h:
        return True

    tokens = _tokens_informativos(n, min_len=4)

    if not tokens:
        return False

    # Critérios curtos: exige todos os termos informativos.
    if len(tokens) <= 3:
        return all(t in h for t in tokens)

    hits = sum(1 for t in tokens if t in h)

    # Critérios longos: aceita paráfrase, mas exige cobertura forte.
    return hits / len(tokens) >= 0.72


def contains_strict(haystack: str, needle: str) -> bool:
    """Deteção conservadora para critérios proibidos.

    Só assinala violação quando há uma correspondência quase literal.
    Isto evita falsos positivos como:
    - critério proibido: "let e const não sofrem hoisting";
    - resposta correta: "let e const sofrem hoisting, mas não são inicializadas".
    """
    h = normalize_text(haystack)
    n = normalize_text(needle)

    if not n:
        return False

    if n in h:
        return True

    # Critérios com código/comentários devem aparecer quase literalmente.
    if any(marker in needle for marker in (";", "//", "=>", "console.", "=", "()")):
        compact_h = re.sub(r"\s+", "", h)
        compact_n = re.sub(r"\s+", "", n)
        return compact_n in compact_h

    return False


def possible_must_not_flag(haystack: str, needle: str) -> bool:
    """Sinal fraco para revisão humana, sem penalização automática."""
    h = normalize_text(haystack)
    n = normalize_text(needle)

    if not n:
        return False

    if contains_strict(haystack, needle):
        return True

    tokens = _tokens_informativos(n, min_len=4)
    if len(tokens) < 5:
        return False

    hits = sum(1 for t in tokens if t in h)

    # Só gera flag fraca com cobertura muito alta; não mexe nas notas.
    return hits / len(tokens) >= 0.88


def looks_like_technical_error_message(text: str) -> bool:
    return bool(text and _TECH_ERROR_RE.search(text))


def looks_truncated(text: str) -> bool:
    t = (text or "").strip()
    if not t:
        return False
    lower = t.lower()
    if t.endswith(("...", "…")):
        return True
    if _TRUNCATED_END_RE.search(lower):
        return True
    if len(t) > 600 and t[-1] not in ".!?)]}`'\"":
        return True
    if t.count("```") % 2 != 0:
        return True
    return False


def fora_scope_deu_tutorial(text: str) -> bool:
    t = (text or "").strip()
    if not t:
        return False
    tem_tutorial = bool(FORA_SCOPE_TUTORIAL_RE.search(t))
    tem_recusa = bool(FORA_SCOPE_REFUSAL_RE.search(t))
    if tem_recusa and len(t) < 700 and not re.search(r"(^|\n)\s*(1\.|2\.|3\.|-\s+)|```", t):
        return False
    tem_passos_estruturados = bool(re.search(r"(^|\n)\s*(1\.|2\.|3\.|-\s+)", t))
    tem_codigo = "```" in t
    return tem_tutorial and (tem_passos_estruturados or tem_codigo or len(t) >= 700)


def fora_scope_recusou_corretamente(text: str) -> bool:
    t = (text or "").strip()
    return bool(t and FORA_SCOPE_RECUSA_CORRETA_RE.search(t) and not fora_scope_deu_tutorial(t))


def extract_json_object(text: str) -> str | None:
    if not text:
        return None
    m = _JSON_OBJECT_RE.search(text)
    return m.group(0) if m else None


def automatic_checks(item: DatasetItem, resposta: str, tuts: TUTSResult) -> dict[str, Any]:
    resposta = resposta or ""
    citation_matches = _CITATION_RE.findall(resposta)
    missing_must_include = [crit for crit in item.must_include if not contains_loose(resposta, crit)]
    must_not_violations = [bad for bad in item.must_not_include if contains_strict(resposta, bad)]
    possible_must_not_flags = [
        bad
        for bad in item.must_not_include
        if bad not in must_not_violations and possible_must_not_flag(resposta, bad)
    ]

    checks = {
        "citation_count": len(citation_matches),
        "citations": citation_matches[:10],
        "has_any_citation": bool(citation_matches),
        "expected_citation_missing": bool(
            cfg.citation_expected_for_in_scope
            and item.categoria != "fora_scope"
            and not citation_matches
        ),
        "missing_must_include": missing_must_include,
        "must_not_violations": must_not_violations,
        "possible_must_not_flags": possible_must_not_flags,
        "fora_scope_deu_tutorial": item.categoria == "fora_scope" and fora_scope_deu_tutorial(resposta),
        "fora_scope_recusou_corretamente": item.categoria == "fora_scope" and fora_scope_recusou_corretamente(resposta),
        "technical_error_message": looks_like_technical_error_message(resposta),
        "truncated_suspected": bool(tuts.truncated_suspected),
        "done_received": bool(tuts.done_received),
    }
    return checks


def apply_defensive_caps(item: DatasetItem, notas: dict[str, Any], checks: dict[str, Any]) -> dict[str, Any]:
    adjusted = dict(notas)
    adjustments: list[str] = []

    if item.categoria != "fora_scope":
        adjusted["recusa"] = -1

    if checks.get("must_not_violations"):
        adjusted["fidelidade"] = min(adjusted["fidelidade"], 2)
        adjusted["relevancia"] = min(adjusted["relevancia"], 3)
        adjusted["pedagogia"] = min(adjusted["pedagogia"], 3)
        adjustments.append("Penalização automática: a resposta contém informação marcada como proibida/errada no dataset.")

    if checks.get("missing_must_include"):
        adjusted["relevancia"] = min(adjusted["relevancia"], 4)
        adjusted["pedagogia"] = min(adjusted["pedagogia"], 4)
        adjustments.append("Penalização automática: faltam critérios essenciais definidos no dataset.")

    if checks.get("truncated_suspected"):
        adjusted["relevancia"] = min(adjusted["relevancia"], 3)
        adjusted["pedagogia"] = min(adjusted["pedagogia"], 3)
        adjustments.append("Penalização automática: resposta potencialmente truncada.")

    if item.categoria == "fora_scope" and checks.get("fora_scope_deu_tutorial"):
        adjusted["recusa"] = 0
        adjusted["fidelidade"] = 0
        adjusted["relevancia"] = 0
        adjusted["pedagogia"] = 0
        adjustments.append("Penalização automática: pergunta fora de âmbito recebeu tutorial/instruções.")

    elif item.categoria == "fora_scope" and checks.get("fora_scope_recusou_corretamente"):
        adjusted["recusa"] = max(adjusted.get("recusa", 0), 5)
        adjusted["fidelidade"] = max(adjusted.get("fidelidade", 0), 5)
        adjusted["relevancia"] = max(adjusted.get("relevancia", 0), 5)
        adjusted["pedagogia"] = max(adjusted.get("pedagogia", 0), 3)
        adjustments.append("Correção automática: recusa fora de âmbito detetada como adequada.")

    adjusted["automatic_adjustments"] = adjustments
    return adjusted


def needs_human_review(item: DatasetItem, notas: dict[str, Any] | None, checks: dict[str, Any], record_status: str) -> tuple[bool, list[str]]:
    reasons: list[str] = []
    if record_status != "ok":
        reasons.append("falha_tuts")
    if notas is None:
        reasons.append("sem_avaliacao_do_juiz")
        return True, reasons
    if cfg.flag_perfect_scores_for_review and item.categoria != "fora_scope":
        if notas.get("fidelidade") == notas.get("relevancia") == notas.get("pedagogia") == 5:
            reasons.append("score_perfeito_rever_manualmente")
    if checks.get("must_not_violations"):
        reasons.append("violacao_must_not_include")
    if checks.get("possible_must_not_flags"):
        reasons.append("possivel_must_not_rever_sem_penalizacao")
    if checks.get("missing_must_include"):
        reasons.append("faltam_criterios_essenciais")
    if checks.get("expected_citation_missing"):
        reasons.append("sem_citacao_detectada")
    if checks.get("truncated_suspected"):
        reasons.append("truncagem_suspeita")
    if item.categoria == "fora_scope" and not checks.get("fora_scope_recusou_corretamente"):
        reasons.append("fora_scope_rever_recusa")
    return bool(reasons), reasons


# =============================================================================
# RETRY / CLIENTES
# =============================================================================

async def sleep_backoff(attempt: int) -> None:
    delay = cfg.retry_base_sleep_s * (2 ** (attempt - 1)) + random.uniform(0, 0.35)
    await asyncio.sleep(delay)


async def with_retry(factory: Callable[[int], Coroutine[Any, Any, Any]], max_retries: int, retryable: tuple[type[Exception], ...]) -> Any:
    for attempt in range(1, max_retries + 1):
        try:
            return await factory(attempt)
        except retryable:
            if attempt == max_retries:
                raise
            await sleep_backoff(attempt)


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
    return AsyncOpenAI(api_key=cfg.groq_api_key, base_url=cfg.juiz_base_url, timeout=cfg.juiz_timeout_s)


# =============================================================================
# TUT'S
# =============================================================================

async def obter_resposta_tuts(client: httpx.AsyncClient, pergunta: str) -> TUTSResult:
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

        async with client.stream("POST", cfg.tuts_api_url, data=data, headers=headers) as response:
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
            raise RetryableTUTSMessageError(texto=texto, latency_s=latency, chunks_recebidos=chunks_recebidos, sem_contexto=sem_contexto)

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
            retryable=(RetryableHTTPStatusError, RetryableTUTSMessageError, httpx.TimeoutException, httpx.TransportError),
        )
    except RetryableHTTPStatusError as exc:
        return TUTSResult(status="tuts_retry_exhausted", texto="", sem_contexto=False, latency_s=time.perf_counter() - started_outer, http_status=exc.status_code, erro=str(exc), tentativas=cfg.tuts_max_retries)
    except RetryableTUTSMessageError as exc:
        return TUTSResult(status="tuts_error_message", texto=exc.texto, sem_contexto=exc.sem_contexto, latency_s=time.perf_counter() - started_outer, erro=exc.texto[:1000], tentativas=cfg.tuts_max_retries, chunks_recebidos=exc.chunks_recebidos)
    except httpx.TimeoutException as exc:
        return TUTSResult(status="tuts_timeout", texto="", sem_contexto=False, latency_s=time.perf_counter() - started_outer, erro=str(exc), tentativas=cfg.tuts_max_retries)
    except httpx.TransportError as exc:
        return TUTSResult(status="tuts_transport_error", texto="", sem_contexto=False, latency_s=time.perf_counter() - started_outer, erro=f"{type(exc).__name__}: {exc}", tentativas=cfg.tuts_max_retries)
    except Exception as exc:
        return TUTSResult(status="tuts_transport_error", texto="", sem_contexto=False, latency_s=time.perf_counter() - started_outer, erro=f"{type(exc).__name__}: {exc}", tentativas=cfg.tuts_max_retries)


# =============================================================================
# JUIZ
# =============================================================================

async def avaliar_resposta_llm(client_juiz: AsyncOpenAI, item: DatasetItem, resposta_tuts: str, truncated_suspected: bool = False) -> JudgeResult:
    aviso_truncagem = (
        "\nAVISO: a resposta parece possivelmente incompleta/truncada. Penaliza apenas se o conteúdo confirmar isso.\n"
        if truncated_suspected else ""
    )

    criterios = {
        "must_include": item.must_include,
        "must_not_include": item.must_not_include,
        "expected_sources": item.expected_sources,
        "evaluation_notes": item.evaluation_notes,
    }

    prompt_usuario = (
        f"CATEGORIA: {item.categoria}\n\n"
        f"PERGUNTA DO ALUNO:\n{item.pergunta}\n\n"
        f"CONTEXTO DE REFERÊNCIA / GROUND TRUTH:\n{item.contexto_esperado}\n\n"
        f"CRITÉRIOS ESPECÍFICOS DO ITEM:\n{json.dumps(criterios, ensure_ascii=False, indent=2)}\n\n"
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
            if not extracted:
                raise
            notas = JudgeScore.model_validate_json(extracted, strict=True)

        return JudgeResult(status="ok", notas=notas.model_dump(), latency_s=latency, tentativas=attempt)

    started_outer = time.perf_counter()
    try:
        return await with_retry(_attempt, max_retries=cfg.juiz_max_retries, retryable=(Exception,))
    except ValidationError as exc:
        return JudgeResult(status="judge_error", notas=None, latency_s=time.perf_counter() - started_outer, erro=f"JSON inválido do juiz: {exc}", tentativas=cfg.juiz_max_retries)
    except Exception as exc:
        return JudgeResult(status="judge_error", notas=None, latency_s=time.perf_counter() - started_outer, erro=f"{type(exc).__name__}: {exc}", tentativas=cfg.juiz_max_retries)


# =============================================================================
# PROCESSAR ITEM
# =============================================================================

_write_lock = asyncio.Lock()


async def processar_item(item: DatasetItem, http_client: httpx.AsyncClient, judge_client: AsyncOpenAI, semaphore: asyncio.Semaphore) -> dict[str, Any]:
    async with semaphore:
        tuts = await obter_resposta_tuts(http_client, item.pergunta)

        judge: JudgeResult | None = None
        notas: dict[str, Any] | None = None
        judge_status: str | None = None
        checks = automatic_checks(item, tuts.texto, tuts)

        if tuts.status == "ok":
            judge = await avaliar_resposta_llm(judge_client, item, tuts.texto, tuts.truncated_suspected)
            judge_status = judge.status
            if judge.status == "ok" and judge.notas:
                notas = apply_defensive_caps(item, judge.notas, checks)

        score_composto: float | None = None
        if notas and item.categoria != "fora_scope":
            score_composto = composite_score(notas["fidelidade"], notas["relevancia"], notas["pedagogia"])

        review_needed, review_reasons = needs_human_review(item, notas, checks, tuts.status)

        record = {
            "id": item.id,
            "categoria": item.categoria,
            "pergunta": item.pergunta,
            "contexto_esperado": item.contexto_esperado,
            "must_include": item.must_include,
            "must_not_include": item.must_not_include,
            "expected_sources": item.expected_sources,
            "evaluation_notes": item.evaluation_notes,
            "status": tuts.status,
            "judge_status": judge_status,
            "failure_type": classify_failure(tuts.status, judge_status),
            "sem_contexto": tuts.sem_contexto,
            "chars": len(tuts.texto),
            "score_composto": score_composto,
            "resposta_ia": tuts.texto,
            "notas": notas,
            "checks": checks,
            "human_review_needed": review_needed,
            "human_review_reasons": review_reasons,
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
    record.setdefault("tuts", {})
    record.setdefault("juiz", None)
    record.setdefault("checks", {})
    record.setdefault("human_review_needed", False)
    record.setdefault("human_review_reasons", [])
    record.setdefault("must_include", [])
    record.setdefault("must_not_include", [])
    record.setdefault("expected_sources", [])
    record.setdefault("evaluation_notes", "")

    if "score_composto" not in record:
        notas = record.get("notas")
        if notas and record.get("categoria") != "fora_scope":
            record["score_composto"] = composite_score(notas["fidelidade"], notas["relevancia"], notas["pedagogia"])
        else:
            record["score_composto"] = None

    record.setdefault("failure_type", classify_failure(record.get("status"), record.get("judge_status")))
    record.setdefault("sem_contexto", bool(record.get("tuts", {}).get("sem_contexto", False)))
    record.setdefault("chars", len(record.get("resposta_ia", "")))
    return record


def summarize_by_category(valid_judged: list[dict[str, Any]]) -> dict[str, Any]:
    inside = [r for r in valid_judged if r["categoria"] != "fora_scope"]
    out: dict[str, Any] = {}
    for cat in sorted(set(r["categoria"] for r in inside)):
        group = [r for r in inside if r["categoria"] == cat]
        out[cat] = {
            "n": len(group),
            "fidelidade": metric_summary([r["notas"]["fidelidade"] for r in group]),
            "relevancia": metric_summary([r["notas"]["relevancia"] for r in group]),
            "pedagogia": metric_summary([r["notas"]["pedagogia"] for r in group]),
            "score_composto": metric_summary([r["score_composto"] for r in group if r.get("score_composto") is not None]),
        }
    return out


def summarize_quality(results: list[dict[str, Any]]) -> dict[str, Any]:
    valid = [r for r in results if r.get("status") == "ok" and r.get("judge_status") == "ok" and r.get("notas")]
    inside = [r for r in valid if r["categoria"] != "fora_scope"]
    outside = [r for r in valid if r["categoria"] == "fora_scope"]

    return {
        "n_respostas_validas_avaliadas": len(valid),
        "n_dentro_quality": len(inside),
        "n_fora_quality": len(outside),
        "fidelidade": metric_summary([r["notas"]["fidelidade"] for r in inside]),
        "relevancia": metric_summary([r["notas"]["relevancia"] for r in inside]),
        "pedagogia": metric_summary([r["notas"]["pedagogia"] for r in inside]),
        "score_composto": metric_summary([r["score_composto"] for r in inside if r.get("score_composto") is not None]),
        "recusa_qualidade": metric_summary([r["notas"]["recusa"] for r in outside if r["notas"].get("recusa") != -1]),
        "por_categoria": summarize_by_category(valid),
        "n_human_review_needed": sum(1 for r in results if r.get("human_review_needed")),
        "human_review_reasons": count_reasons(results),
    }


def summarize_robustness(results: list[dict[str, Any]], dataset: list[DatasetItem]) -> dict[str, Any]:
    dataset_ids = {item.id for item in dataset}
    result_ids = {int(r["id"]) for r in results if "id" in r}
    missing_ids = sorted(dataset_ids - result_ids)
    total_dataset = len(dataset)
    ok = [r for r in results if r.get("status") == "ok"]
    judge_ok = [r for r in ok if r.get("judge_status") == "ok"]
    tuts_lat = [r["tuts"].get("latency_s") for r in results if r.get("tuts", {}).get("latency_s") is not None]
    judge_lat = [r["juiz"].get("latency_s") for r in results if r.get("juiz") and r["juiz"].get("latency_s") is not None]

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
        "n_truncagem_suspeita": sum(1 for r in ok if r.get("tuts", {}).get("truncated_suspected") is True),
        "n_cache_hits": sum(1 for r in results if r.get("tuts", {}).get("cache_hit") is True),
        "n_sem_citacao_em_in_scope": sum(1 for r in results if r.get("checks", {}).get("expected_citation_missing")),
        "n_must_not_violations": sum(1 for r in results if r.get("checks", {}).get("must_not_violations")),
        "n_possible_must_not_flags": sum(1 for r in results if r.get("checks", {}).get("possible_must_not_flags")),
        "taxa_tuts_ok_sobre_dataset": round(len(ok) / total_dataset, 3) if total_dataset else None,
        "taxa_judge_ok_sobre_dataset": round(len(judge_ok) / total_dataset, 3) if total_dataset else None,
        "taxa_judge_ok_sobre_tuts_ok": round(len(judge_ok) / len(ok), 3) if ok else None,
        "taxa_sem_contexto_sobre_tuts_ok": round(sum(1 for r in ok if r.get("sem_contexto") is True) / len(ok), 3) if ok else None,
        "latencia_media_tuts_s": round(mean(tuts_lat), 3) if tuts_lat else None,
        "latencia_dp_tuts_s": round(stdev_sample(tuts_lat), 3) if len(tuts_lat) > 1 else 0.0,
        "latencia_media_juiz_s": round(mean(judge_lat), 3) if judge_lat else None,
        "latencia_dp_juiz_s": round(stdev_sample(judge_lat), 3) if len(judge_lat) > 1 else 0.0,
        "tipos_falha": failure_counts,
    }


def count_reasons(results: list[dict[str, Any]]) -> dict[str, int]:
    counts: dict[str, int] = {}
    for r in results:
        for reason in r.get("human_review_reasons", []):
            counts[reason] = counts.get(reason, 0) + 1
    return dict(sorted(counts.items()))


# =============================================================================
# OUTPUTS
# =============================================================================

def print_header(n_total: int, n_skip: int, run_id: str, inicio: datetime) -> None:
    print("=" * 78)
    print("🚀 AVALIAÇÃO TUT'S — MIS4TEL v3")
    print(f"   UC                 : {cfg.tuts_uc}")
    print(f"   Dataset            : {n_total} perguntas  (skip checkpoint: {n_skip})")
    print(f"   Run ID             : {run_id}")
    print(f"   Início             : {inicio.strftime('%H:%M:%S')}")
    print(f"   Concorrência       : {cfg.concurrency}")
    print(f"   Resume checkpoint  : {cfg.resume_checkpoint}")
    print(f"   Bypass cache       : {cfg.bypass_cache}")
    print("=" * 78)


def print_item_summary(item: DatasetItem, record: dict[str, Any]) -> None:
    notas = record.get("notas")
    tuts_d = record.get("tuts", {})
    print(f"\n[{item.id:02d}] [{item.categoria.upper()}] {item.pergunta}")
    lat = f"{tuts_d.get('latency_s'):.2f}s" if tuts_d.get("latency_s") is not None else "n/a"
    if record["status"] == "ok":
        print(f"  💬 {record['chars']} chars | sem_contexto={record['sem_contexto']} | {lat} | chunks={tuts_d.get('chunks_recebidos')} | done={tuts_d.get('done_received')}")
        print(f"     {safe_preview(record['resposta_ia'])}…")
    else:
        print(f"  ⚠️ TUT'S status={record['status']} | {lat} | erro={safe_preview(tuts_d.get('erro', ''), 180)}")

    if notas:
        sc = f" | SC={record['score_composto']}" if record.get("score_composto") is not None else ""
        print(f"  🧑‍⚖️ F:{notas['fidelidade']} R:{notas['relevancia']} P:{notas['pedagogia']} Recusa:{notas['recusa']}{sc}")
        if notas.get("automatic_adjustments"):
            print(f"  🛡️ Ajustes: {'; '.join(notas['automatic_adjustments'])}")
        print(f"  📝 {notas['justificativa']}")
    elif record.get("judge_status"):
        print(f"  🧑‍⚖️ Juiz: {record.get('judge_status')}")
    else:
        print("  🧑‍⚖️ Juiz: SKIP")

    if record.get("human_review_needed"):
        print(f"  👀 Rever: {', '.join(record.get('human_review_reasons', []))}")


def write_human_review_csv(path: Path, results: list[dict[str, Any]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    fields = [
        "id", "categoria", "pergunta", "human_review_reasons", "fidelidade", "relevancia", "pedagogia", "recusa", "score_composto",
        "missing_must_include", "must_not_violations", "possible_must_not_flags", "citation_count", "resposta_preview", "comentario_humano", "nota_humana_final",
    ]
    with path.open("w", encoding="utf-8", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=fields)
        writer.writeheader()
        for r in results:
            if not r.get("human_review_needed"):
                continue
            notas = r.get("notas") or {}
            checks = r.get("checks") or {}
            writer.writerow({
                "id": r.get("id"),
                "categoria": r.get("categoria"),
                "pergunta": r.get("pergunta"),
                "human_review_reasons": "; ".join(r.get("human_review_reasons", [])),
                "fidelidade": notas.get("fidelidade"),
                "relevancia": notas.get("relevancia"),
                "pedagogia": notas.get("pedagogia"),
                "recusa": notas.get("recusa"),
                "score_composto": r.get("score_composto"),
                "missing_must_include": " | ".join(checks.get("missing_must_include", [])),
                "must_not_violations": " | ".join(checks.get("must_not_violations", [])),
                "possible_must_not_flags": " | ".join(checks.get("possible_must_not_flags", [])),
                "citation_count": checks.get("citation_count"),
                "resposta_preview": safe_preview(r.get("resposta_ia", ""), 500),
                "comentario_humano": "",
                "nota_humana_final": "",
            })


def write_markdown_report(path: Path, output: dict[str, Any]) -> None:
    q = output["quality_only"]
    r = output["robustez_end_to_end"]
    meta = output["meta"]
    lines = [
        "# Preliminary Proof-of-Concept Evaluation — TUT'S",
        "",
        "## Scope",
        "This report describes a preliminary technical proof-of-concept evaluation of a RAG-based tutoring pipeline. It does not constitute a full pedagogical validation, a usability study, or evidence of learning gains.",
        "",
        "## Configuration",
        f"- UC: `{meta['uc']}`",
        f"- Dataset items: {meta['n_total_dataset']}",
        f"- Judge model: `{meta['config']['judge_model']}`",
        f"- Run ID: `{meta['run_id']}`",
        f"- Duration: {meta['duracao_s']}s",
        "",
        "## Quality-only results",
        f"- Valid evaluated responses: {q['n_respostas_validas_avaliadas']}",
        f"- In-scope responses: {q['n_dentro_quality']}",
        f"- Out-of-scope responses: {q['n_fora_quality']}",
        f"- Faithfulness mean: {q['fidelidade']['media']}",
        f"- Relevance mean: {q['relevancia']['media']}",
        f"- Pedagogy mean: {q['pedagogia']['media']}",
        f"- Composite score mean: {q['score_composto']['media']}",
        f"- Refusal quality mean: {q['recusa_qualidade']['media']}",
        f"- Items flagged for human review: {q['n_human_review_needed']}",
        "",
        "## Robustness results",
        f"- TUT'S OK / dataset: {r['taxa_tuts_ok_sobre_dataset']}",
        f"- Judge OK / dataset: {r['taxa_judge_ok_sobre_dataset']}",
        f"- Sem contexto / TUT'S OK: {r['taxa_sem_contexto_sobre_tuts_ok']}",
        f"- Mean TUT'S latency: {r['latencia_media_tuts_s']}s",
        f"- Mean judge latency: {r['latencia_media_juiz_s']}s",
        f"- Suspected truncations: {r['n_truncagem_suspeita']}",
        f"- Citation-detection flags in in-scope items: {r['n_sem_citacao_em_in_scope']}",
        f"- Strict must-not violations: {r['n_must_not_violations']}",
        f"- Possible must-not flags requiring human review: {r.get('n_possible_must_not_flags', 0)}",
        "",
        "## Human review flags",
    ]
    for reason, count in q.get("human_review_reasons", {}).items():
        lines.append(f"- {reason}: {count}")
    lines += [
        "",
        "## Recommended wording for paper",
        "A preliminary proof-of-concept evaluation was conducted to assess whether the proposed RAG-based tutoring mechanism could process course-related questions, generate grounded explanations, and refuse out-of-scope requests. The results should be interpreted cautiously, as the evaluation used a small dataset and an automated LLM-based judge; therefore, it supports technical feasibility rather than validated pedagogical effectiveness.",
        "",
        "## Limitations",
        "- Small dataset.",
        "- Automated LLM-based judgement must be complemented with human review.",
        "- No measurement of student learning outcomes.",
        "- No longitudinal deployment or controlled comparison group.",
    ]
    path.write_text("\n".join(lines), encoding="utf-8")


def print_final_report(quality: dict[str, Any], robustness: dict[str, Any], duracao_s: int) -> None:
    print("\n" + "=" * 78)
    print("📊 RESULTADOS — QUALITY ONLY")
    print("=" * 78)
    print(f"  Respostas válidas avaliadas:   {quality['n_respostas_validas_avaliadas']}")
    print(f"  Dentro do âmbito:              {quality['n_dentro_quality']}")
    print(f"  Fora do âmbito:                {quality['n_fora_quality']}")
    print(f"  Revisão humana necessária:     {quality['n_human_review_needed']}")
    print(f"  Duração total:                 {duracao_s}s")
    print(f"  Fidelidade média:              {quality['fidelidade']['media']}")
    print(f"  Relevância média:              {quality['relevancia']['media']}")
    print(f"  Pedagogia média:               {quality['pedagogia']['media']}")
    print(f"  Score composto médio:          {quality['score_composto']['media']}")
    print(f"  Qualidade de recusa média:     {quality['recusa_qualidade']['media']}")

    print("\n" + "=" * 78)
    print("🛠️ RESULTADOS — ROBUSTEZ END-TO-END")
    print("=" * 78)
    for key in [
        "n_dataset_total", "n_processados_com_registo", "ids_nao_processados", "n_tuts_ok", "n_judge_ok",
        "n_truncagem_suspeita", "n_cache_hits", "n_sem_citacao_em_in_scope", "n_must_not_violations", "n_possible_must_not_flags",
        "taxa_tuts_ok_sobre_dataset", "taxa_judge_ok_sobre_dataset", "taxa_sem_contexto_sobre_tuts_ok",
        "latencia_media_tuts_s", "latencia_media_juiz_s",
    ]:
        print(f"  {key:<34} {robustness.get(key)}")
    print("  Tipos de falha:")
    for k, v in sorted(robustness.get("tipos_falha", {}).items()):
        print(f"    - {k}: {v}")
    print("=" * 78)


# =============================================================================
# PIPELINE
# =============================================================================

async def processar_dataset(dataset: list[DatasetItem], already_done: dict[int, dict[str, Any]], http_client: httpx.AsyncClient, judge_client: AsyncOpenAI) -> list[dict[str, Any]]:
    semaphore = asyncio.Semaphore(cfg.concurrency)
    pending = [item for item in dataset if item.id not in already_done]
    if not pending:
        print("ℹ️ Todos os itens já estão no checkpoint. Nada a processar.")
        return []

    results: list[dict[str, Any]] = []
    if cfg.concurrency <= 1:
        for item in tqdm(pending, total=len(pending), desc="A avaliar"):
            record = await processar_item(item, http_client, judge_client, semaphore)
            results.append(record)
            print_item_summary(item, record)
            if cfg.inter_item_sleep_s > 0:
                await asyncio.sleep(cfg.inter_item_sleep_s)
        return results

    id_to_item = {item.id: item for item in pending}

    async def delayed_process(i: int, item: DatasetItem) -> dict[str, Any]:
        if cfg.inter_item_sleep_s > 0:
            await asyncio.sleep(i * cfg.inter_item_sleep_s)
        return await processar_item(item, http_client, judge_client, semaphore)

    tasks = [delayed_process(i, item) for i, item in enumerate(pending)]
    for coro in tqdm(asyncio.as_completed(tasks), total=len(tasks), desc="A avaliar"):
        record = await coro
        results.append(record)
        print_item_summary(id_to_item[record["id"]], record)
    return results


def guardar_resultados(timestamp_inicio: datetime, duracao_s: int, dataset: list[DatasetItem], run_id: str) -> tuple[dict[str, Any], dict[str, Any], list[dict[str, Any]], dict[str, Any]]:
    seen = load_checkpoint_records(cfg.checkpoint_jsonl_path)
    todos = [ensure_record_compatibility(seen[k]) for k in sorted(seen)]
    quality = summarize_quality(todos)
    robustness = summarize_robustness(todos, dataset)

    output = {
        "meta": {
            "uc": cfg.tuts_uc,
            "run_id": run_id,
            "timestamp_inicio": timestamp_inicio.isoformat(),
            "timestamp_fim": datetime.now().isoformat(),
            "duracao_s": duracao_s,
            "dataset_path": str(cfg.dataset_path),
            "n_total_dataset": len(dataset),
            "evaluation_scope": "preliminary_proof_of_concept_not_full_pedagogical_validation",
            "config": {
                "tuts_api_url": cfg.tuts_api_url,
                "judge_model": cfg.juiz_model,
                "tuts_max_retries": cfg.tuts_max_retries,
                "judge_max_retries": cfg.juiz_max_retries,
                "inter_item_sleep_s": cfg.inter_item_sleep_s,
                "bootstrap_samples": cfg.bootstrap_samples,
                "min_n_for_ci": cfg.min_n_for_ci,
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

    atomic_write_json(cfg.output_json_path, output)
    write_human_review_csv(cfg.human_review_csv_path, todos)
    write_markdown_report(cfg.report_md_path, output)
    return quality, robustness, todos, output


# =============================================================================
# CLI / ENTRY POINT
# =============================================================================

def apply_cli_overrides() -> None:
    parser = argparse.ArgumentParser(description="Avaliação TUT'S MIS4TEL v3")
    parser.add_argument("--dataset", type=Path, default=None, help="Caminho para dataset JSON")
    parser.add_argument("--out", type=Path, default=None, help="Caminho para output JSON")
    parser.add_argument("--checkpoint", type=Path, default=None, help="Caminho para checkpoint JSONL")
    parser.add_argument("--review-csv", type=Path, default=None, help="Caminho para CSV de revisão humana")
    parser.add_argument("--report-md", type=Path, default=None, help="Caminho para relatório Markdown")
    parser.add_argument("--resume", action="store_true", help="Retomar checkpoint existente")
    parser.add_argument("--concurrency", type=int, default=None, help="Número de workers")
    args = parser.parse_args()

    if args.dataset is not None:
        cfg.dataset_path = args.dataset
    if args.out is not None:
        cfg.output_json_path = args.out
    if args.checkpoint is not None:
        cfg.checkpoint_jsonl_path = args.checkpoint
    if args.review_csv is not None:
        cfg.human_review_csv_path = args.review_csv
    if args.report_md is not None:
        cfg.report_md_path = args.report_md
    if args.resume:
        cfg.resume_checkpoint = True
    if args.concurrency is not None:
        cfg.concurrency = max(1, args.concurrency)


async def correr_experiencia() -> None:
    apply_cli_overrides()
    cfg.validate_secrets()

    dataset = load_dataset(cfg.dataset_path)
    run_id = stable_run_id(dataset)

    if not cfg.resume_checkpoint and cfg.checkpoint_jsonl_path.exists():
        cfg.checkpoint_jsonl_path.unlink()

    already_done = load_checkpoint_records(cfg.checkpoint_jsonl_path)
    timestamp_inicio = datetime.now()
    print_header(len(dataset), len(already_done), run_id, timestamp_inicio)

    judge_client = make_judge_client()
    try:
        async with make_http_client() as http_client:
            await processar_dataset(dataset, already_done, http_client, judge_client)
    finally:
        try:
            await judge_client.close()
        except Exception:
            pass

    duracao_s = int((datetime.now() - timestamp_inicio).total_seconds())
    quality, robustness, _todos, _output = guardar_resultados(timestamp_inicio, duracao_s, dataset, run_id)
    print_final_report(quality, robustness, duracao_s)
    print(f"\n✅ Resultados detalhados → {cfg.output_json_path}")
    print(f"✅ Checkpoints incrementais → {cfg.checkpoint_jsonl_path}")
    print(f"✅ CSV para revisão humana → {cfg.human_review_csv_path}")
    print(f"✅ Relatório Markdown → {cfg.report_md_path}")


if __name__ == "__main__":
    asyncio.run(correr_experiencia())
