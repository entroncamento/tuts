import logging
from enum import Enum
from pathlib import Path
from typing import Optional

from dotenv import load_dotenv
from pydantic import field_validator, model_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


# ── PATHS BASE ────────────────────────────────────────────────────────────────
BASE_DIR = Path(__file__).resolve().parent
WORKSPACE_ROOT = BASE_DIR.parent
LARAVEL_ROOT = WORKSPACE_ROOT / "tuts-core"
ENV_FILE = BASE_DIR / ".env"

load_dotenv(dotenv_path=ENV_FILE, override=False)

FAISS_INDEX_FILE = "index.faiss"
MANIFEST_FILE = "manifest.json"


# ── LOGGING ───────────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s | %(levelname)s | %(name)s | %(message)s",
    datefmt="%Y-%m-%dT%H:%M:%S",
)

logger = logging.getLogger("tuts")


class PreferenciaEnum(str, Enum):
    default = "default"
    visual = "visual"
    plano = "plano"
    quiz = "quiz"
    feynman = "feynman"


class Settings(BaseSettings):
    # ── AMBIENTE ──────────────────────────────────────────────────────────────
    app_env: str = "local"

    # ── SEGREDOS / TOKENS ─────────────────────────────────────────────────────
    groq_api_key: str

    iaedu_api_key: str = ""
    iaedu_agent_id: str = ""
    iaedu_channel_id: str = ""
    professor_api_key: str = ""

    internal_token: str

    @field_validator("internal_token", "professor_api_key")
    @classmethod
    def validar_token_forte(cls, v: str) -> str:
        v = (v or "").strip()

        # professor_api_key pode estar vazio em dev se ainda não estiveres a usar
        if not v:
            return v

        if len(v) < 32:
            raise ValueError(
                "O token interno/API professor é demasiado fraco. "
                "Deve ter pelo menos 32 caracteres."
            )

        return v

    # ── ORIGENS / URLS ────────────────────────────────────────────────────────
    frontend_origin: str = "http://localhost:5173,http://127.0.0.1:5173"
    laravel_url: str = "http://127.0.0.1:8000"

    # ── PATHS ─────────────────────────────────────────────────────────────────
    uc_json_path: str = str(BASE_DIR / "database" / "data" / "cadeiras_mtc.json")
    base_faiss_dir: str = str(BASE_DIR / "faiss_db")
    sqlite_db: str = str(BASE_DIR / "tuts_logs.db")

    pdf_storage_dir: str = str(
        LARAVEL_ROOT / "storage" / "app" / "public" / "pdfs"
    )

    service_account_file: str = str(BASE_DIR / "service_account.json")

      # ── RETRIEVAL / OCR / INGESTÃO ────────────────────────────────────────────
    faiss_k: int = 8
    bm25_k: int = 6
    final_k: int = 3
    score_minimo: float = -10.0

    # Score mínimo do reranker para considerar que há contexto suficiente
    rerank_min_score_contexto: float = -8

    max_image_mb: int = 4
    chunk_size: int = 1200
    chunk_overlap: int = 250
    max_upload_mb: int = 50
    semantic_cache_threshold: float = 0.92

    # Cache semântica
    semantic_cache_enabled: bool = True

    # Buffer SSE para não enviar chunks demasiado pequenos
    sse_buffer_chars: int = 180

       # Modelos locais
    embedding_model: str = "paraphrase-multilingual-MiniLM-L12-v2"
    reranker_model: str = "cross-encoder/mmarco-mMiniLMv2-L12-H384-v1"
    rrf_k: int = 60

    # ── SERVIDOR ──────────────────────────────────────────────────────────────
    server_host: str = "127.0.0.1"
    server_port: int = 8001

    # ── REDIS ─────────────────────────────────────────────────────────────────
    redis_host: str = "127.0.0.1"
    redis_port: int = 6379
    redis_password: Optional[str] = None
    redis_ssl: bool = False
    redis_db: int = 0
    redis_cache_ttl_dias: int = 7

    # ── FEATURES ──────────────────────────────────────────────────────────────
    expose_public_pdfs: bool = False
    google_calendar_target_id: Optional[str] = None

    model_config = SettingsConfigDict(
        env_file=str(ENV_FILE),
        env_file_encoding="utf-8",
        extra="ignore",
        
    )

    @model_validator(mode="after")
    def verificar_seguranca_producao(self):
        if self.app_env == "production":
            if self.expose_public_pdfs:
                raise ValueError(
                    "CRITICAL: expose_public_pdfs não pode estar True em produção."
                )

            if self.server_host == "0.0.0.0":
                logger.warning(
                    "ATENÇÃO: server_host está 0.0.0.0 em produção. "
                    "Garante que está atrás de rede privada/reverse proxy."
                )

            if not self.redis_password and self.redis_host != "127.0.0.1":
                logger.warning(
                    "ATENÇÃO: Redis remoto configurado sem password em produção."
                )

        return self


try:
    settings = Settings()
except Exception as exc:
    raise RuntimeError(f"CRITICAL: Configuração inválida — {exc}") from exc


logger.info("[CONFIG] TUT'S RAG a iniciar no ambiente: %s", settings.app_env.upper())
logger.info("[CONFIG] BASE_DIR=.../%s", BASE_DIR.name)
logger.info("[CONFIG] pdf_storage_dir=.../%s", Path(settings.pdf_storage_dir).name)
logger.info("[CONFIG] expose_public_pdfs=%s", settings.expose_public_pdfs)


# ── FLAGS GLOBAIS ─────────────────────────────────────────────────────────────
usar_ocr: bool = False
usar_gpu: bool = False

# Idealmente troca isto por um hash real/fixo do modelo usado.
embedding_revision: str | None = "d1d8a3"