import logging
import json
from enum import Enum
from pydantic_settings import BaseSettings, SettingsConfigDict
from slowapi import Limiter
from slowapi.util import get_remote_address

FAISS_INDEX_FILE = "index.faiss"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s | %(levelname)s | %(name)s | %(message)s",
    datefmt="%Y-%m-%dT%H:%M:%S",
)
logger = logging.getLogger("tuts")

class Settings(BaseSettings):
    iaedu_api_key: str
    iaedu_agent_id: str
    iaedu_channel_id: str
    professor_api_key: str

    internal_token: str
    frontend_origin: str = "http://localhost:5173"

    uc_json_path: str = "database/data/cadeiras_mtc.json"

    faiss_k: int = 8
    bm25_k: int = 6
    final_k: int = 3
    score_minimo: float = -10.0
    max_image_mb: int = 4
    chunk_size: int = 1200
    chunk_overlap: int = 250
    max_upload_mb: int = 50

    resposta_cache_ttl: int = 300
    resposta_cache_size: int = 512
    semantic_cache_threshold: float = 0.92
    semantic_cache_maxsize: int = 100

    embedding_model: str = "paraphrase-multilingual-MiniLM-L12-v2"
    reranker_model: str = "cross-encoder/mmarco-mMiniLMv2-L12-H384-v1"

    rrf_k: int = 60
    base_faiss_dir: str = "faiss_db"
    sqlite_db: str = "tuts_logs.db"
    server_host: str = "127.0.0.1"
    server_port: int = 8001

    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

try:
    settings = Settings()
except Exception as exc:
    raise RuntimeError(f"CRITICAL: Configuração inválida — {exc}") from exc

limiter = Limiter(key_func=get_remote_address)

def _carregar_ucs(path: str) -> type[Enum]:
    try:
        with open(path, encoding="utf-8") as f:
            dados = json.load(f)
        membros = {entry["nome_uc"]: entry["nome_uc"] for entry in dados}
        return Enum("UCEnum", membros)
    except FileNotFoundError:
        raise RuntimeError(f"CRITICAL: Ficheiro não encontrado em '{path}'")

UCEnum = _carregar_ucs(settings.uc_json_path)

# APENAS OS 5 MODOS ORIGINAIS (COM DEFAULT)
class PreferenciaEnum(str, Enum):
    default = "default"
    visual  = "visual"
    plano   = "plano"
    quiz    = "quiz"
    feynman = "feynman"