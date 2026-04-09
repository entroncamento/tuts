import logging
import json
import os
from enum import Enum
from pydantic_settings import BaseSettings, SettingsConfigDict

FAISS_INDEX_FILE = "index.faiss"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s | %(levelname)s | %(name)s | %(message)s",
    datefmt="%Y-%m-%dT%H:%M:%S",
)
logger = logging.getLogger("tuts")

class Settings(BaseSettings):
    # 🔥 A TUA CHAVE AGORA VIVE AQUI, PROTEGIDA:
    groq_api_key: str

    iaedu_api_key: str
    iaedu_agent_id: str
    iaedu_channel_id: str
    professor_api_key: str

    internal_token: str
    frontend_origin: str = "http://localhost:5173"
    laravel_url: str = "http://127.0.0.1:8000"

    uc_json_path: str = "database/data/cadeiras_mtc.json" # Podes apagar isto mais tarde se já não usares

    faiss_k: int = 8
    bm25_k: int = 6
    final_k: int = 3
    score_minimo: float = -10.0
    max_image_mb: int = 4
    chunk_size: int = 1200
    chunk_overlap: int = 250
    max_upload_mb: int = 50

    semantic_cache_threshold: float = 0.92

    embedding_model: str = "paraphrase-multilingual-MiniLM-L12-v2"
    reranker_model: str = "cross-encoder/mmarco-mMiniLMv2-L12-H384-v1"

    rrf_k: int = 60
    base_faiss_dir: str = "faiss_db"
    sqlite_db: str = "tuts_logs.db"
    server_host: str = "0.0.0.0"
    server_port: int = 8001

    redis_host: str = "localhost"
    redis_port: int = 6379
    redis_cache_ttl_dias: int = 7

    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

try:
    settings = Settings()
except Exception as exc:
    raise RuntimeError(f"CRITICAL: Configuração inválida — {exc}") from exc

# 🔥 LIMPÁMOS O LIMITER E A CARGA DO UCEnum DAQUI!

class PreferenciaEnum(str, Enum):
    default = "default"
    visual  = "visual"
    plano   = "plano"
    quiz    = "quiz"
    feynman = "feynman"