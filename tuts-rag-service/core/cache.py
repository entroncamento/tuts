import asyncio
import json
import os
import re
import uuid
from collections import defaultdict

import numpy as np
import redis.asyncio as redis
from langchain_community.retrievers import BM25Retriever
from langchain_community.vectorstores import FAISS
from redis.commands.search.field import TagField, TextField, VectorField
from redis.commands.search.index_definition import IndexDefinition, IndexType
from redis.commands.search.query import Query

from config import MANIFEST_FILE, logger, settings
from core.utils import limpar_nome_uc


faiss_cache: dict[str, FAISS] = {}
bm25_cache: dict[str, BM25Retriever] = {}
docs_cache: dict[str, list] = {}

_cache_locks: dict[str, asyncio.Lock] = defaultdict(asyncio.Lock)
_ingestao_locks: dict[str, asyncio.Lock] = defaultdict(asyncio.Lock)


# ── REDIS ────────────────────────────────────────────────────────────────────

redis_client = redis.Redis(
    host=settings.redis_host,
    port=settings.redis_port,
    password=getattr(settings, "redis_password", None),
    db=getattr(settings, "redis_db", 0),
    ssl=getattr(settings, "redis_ssl", False),
    decode_responses=False,
)

# paraphrase-multilingual-MiniLM-L12-v2 usa 384 dimensões
EMBEDDING_DIM = int(getattr(settings, "embedding_dim", 384))

# Nova versão para evitar conflitos com índices antigos/corrompidos
INDEX_NAME = "idx:semantic_cache_v3"
CACHE_PREFIX = "cache_v3:"

redis_cache_disponivel = True


async def fechar_conexoes() -> None:
    """Fecha ligações Redis no shutdown da app."""
    try:
        await redis_client.aclose()
    except Exception:
        pass


def _normalizar_uc_cache(uc: str) -> str:
    return limpar_nome_uc(uc or "")


def _normalizar_versao_cache(versao: str) -> str:
    bruto = (versao or "sem_versao").strip().lower()
    bruto = re.sub(r"[^a-z0-9_]", "_", bruto)
    bruto = re.sub(r"_+", "_", bruto).strip("_")

    return bruto or "sem_versao"


def _escape_tag_value(valor: str) -> str:
    """
    Escapa caracteres especiais para queries TAG do RediSearch.
    Mesmo com UCs normalizadas, isto evita crashes em valores inesperados.
    """

    valor = valor or ""

    return re.sub(
        r'([\\{}\[\]\(\)\-:"\'@~&|!<>])',
        r"\\\1",
        valor,
    )


def _redis_erro_indica_indice_inexistente(exc: Exception) -> bool:
    msg = str(exc).lower()

    return (
        "unknown index" in msg
        or "unknown index name" in msg
        or "no such index" in msg
        or "index does not exist" in msg
    )


def _redis_erro_indica_modulo_ausente(exc: Exception) -> bool:
    msg = str(exc).lower()

    return (
        "unknown command" in msg
        or ("ft.search" in msg and "unknown" in msg)
        or ("module" in msg and "search" in msg)
    )


def obter_versao_uc(uc: str) -> str:
    uc_norm = _normalizar_uc_cache(uc)
    manifest_path = os.path.join(settings.base_faiss_dir, uc_norm, MANIFEST_FILE)

    if not os.path.exists(manifest_path):
        return "sem_versao"

    try:
        with open(manifest_path, "r", encoding="utf-8") as f:
            manifest = json.load(f)

        return _normalizar_versao_cache(manifest.get("version", "sem_versao"))

    except Exception as exc:
        logger.warning(
            "Falha a ler manifest da UC %s: %s",
            uc_norm,
            type(exc).__name__,
        )

        return "sem_versao"


async def init_redis_index() -> None:
    """
    Inicializa o índice vetorial do Redis.

    Se Redis/RediSearch não estiver disponível, a cache semântica é desativada
    sem partir o RAG.
    """

    global redis_cache_disponivel

    if not getattr(settings, "semantic_cache_enabled", True):
        redis_cache_disponivel = False
        logger.info("Cache semântica Redis desativada por configuração.")
        return

    try:
        await redis_client.ping()

    except Exception as exc:
        redis_cache_disponivel = False

        logger.warning(
            "Redis indisponível. Cache semântica desativada. erro=%s | detalhe=%s",
            type(exc).__name__,
            str(exc)[:200],
        )

        return

    try:
        await redis_client.ft(INDEX_NAME).info()

        redis_cache_disponivel = True
        logger.info("Índice Redis %s já existe — a saltar criação.", INDEX_NAME)

        return

    except Exception as exc:
        if _redis_erro_indica_modulo_ausente(exc):
            redis_cache_disponivel = False

            logger.warning(
                "Redis está ativo, mas RediSearch/Redis Stack não parece disponível. "
                "Cache semântica desativada. detalhe=%s",
                str(exc)[:200],
            )

            return

        if not _redis_erro_indica_indice_inexistente(exc):
            redis_cache_disponivel = False

            logger.warning(
                "Erro inesperado ao verificar índice Redis. "
                "Cache semântica desativada. erro=%s | detalhe=%s",
                type(exc).__name__,
                str(exc)[:300],
            )

            return

    try:
        logger.info("A criar índice vetorial Redis %s...", INDEX_NAME)

        schema = (
            TagField("uc"),
            TagField("versao_uc"),
            TextField("resposta_json"),
            VectorField(
                "embedding",
                "FLAT",
                {
                    "TYPE": "FLOAT32",
                    "DIM": EMBEDDING_DIM,
                    "DISTANCE_METRIC": "COSINE",
                },
            ),
        )

        definition = IndexDefinition(
            prefix=[CACHE_PREFIX],
            index_type=IndexType.HASH,
        )

        await redis_client.ft(INDEX_NAME).create_index(
            fields=schema,
            definition=definition,
        )

        redis_cache_disponivel = True
        logger.info("Índice vetorial Redis criado com sucesso: %s", INDEX_NAME)

    except Exception as exc:
        redis_cache_disponivel = False

        logger.warning(
            "Falha ao criar índice Redis. Cache semântica desativada. "
            "erro=%s | detalhe=%s",
            type(exc).__name__,
            str(exc)[:300],
        )


async def procurar_cache_redis(
    uc: str,
    versao_uc: str,
    query_emb: list[float],
    threshold: float,
) -> dict | None:
    """
    Procura resposta semanticamente semelhante no Redis.

    Se o Redis falhar, não interrompe o RAG: apenas ignora a cache.
    """

    if not getattr(settings, "semantic_cache_enabled", True):
        return None

    if not redis_cache_disponivel:
        return None

    try:
        uc_norm = _normalizar_uc_cache(uc)
        versao_norm = _normalizar_versao_cache(versao_uc)

        uc_tag = _escape_tag_value(uc_norm)
        versao_tag = _escape_tag_value(versao_norm)

        emb_bytes = np.array(query_emb, dtype=np.float32).tobytes()
        max_dist = 1.0 - float(threshold)

        query_text = (
            f"(@uc:{{{uc_tag}}} @versao_uc:{{{versao_tag}}})"
            f"=>[KNN 1 @embedding $vec AS distance]"
        )

        query = (
            Query(query_text)
            .sort_by("distance")
            .return_fields("resposta_json", "distance")
            .dialect(2)
        )

        result = await redis_client.ft(INDEX_NAME).search(
            query,
            query_params={"vec": emb_bytes},
        )

        if not result.docs:
            return None

        distance = float(result.docs[0].distance)

        if distance <= max_dist:
            logger.info(
                "Cache hit no Redis. UC=%s versão=%s dist=%.3f",
                uc_norm,
                versao_norm,
                distance,
            )

            resposta_raw = result.docs[0].resposta_json

            if isinstance(resposta_raw, bytes):
                resposta_raw = resposta_raw.decode("utf-8")

            return json.loads(resposta_raw)

    except Exception as exc:
        logger.warning(
            "Erro ao ler cache do Redis. A ignorar cache nesta pergunta. "
            "uc=%s | erro=%s | detalhe=%s",
            uc,
            type(exc).__name__,
            str(exc)[:300],
        )

    return None


async def guardar_cache_redis(
    uc: str,
    versao_uc: str,
    emb: list[float],
    resposta: dict,
    ttl_dias: int = 7,
) -> None:
    """
    Guarda resposta segura no Redis.

    Não guarda pergunta original nem query expandida para evitar fuga de dados
    entre utilizadores.
    """

    if not getattr(settings, "semantic_cache_enabled", True):
        return

    if not redis_cache_disponivel:
        return

    try:
        resposta_segura = {
            "resposta_stu": resposta.get("resposta_stu"),
            "sem_contexto": resposta.get("sem_contexto"),
            "fontes_consultadas": resposta.get("fontes_consultadas", []),
        }

        if "calendario" in resposta:
            resposta_segura["calendario"] = resposta["calendario"]

        uc_norm = _normalizar_uc_cache(uc)
        versao_norm = _normalizar_versao_cache(versao_uc)

        key = f"{CACHE_PREFIX}{uc_norm}:{uuid.uuid4().hex}"
        emb_bytes = np.array(emb, dtype=np.float32).tobytes()

        await redis_client.hset(
            key,
            mapping={
                "uc": uc_norm,
                "versao_uc": versao_norm,
                "embedding": emb_bytes,
                "resposta_json": json.dumps(resposta_segura, ensure_ascii=False),
            },
        )

        await redis_client.expire(key, int(ttl_dias) * 24 * 60 * 60)

    except Exception as exc:
        logger.warning(
            "Erro ao guardar cache no Redis. A resposta ao aluno não é afetada. "
            "erro=%s | detalhe=%s",
            type(exc).__name__,
            str(exc)[:300],
        )


async def invalidar_cache_redis_uc(uc: str) -> None:
    if not redis_cache_disponivel:
        return

    try:
        uc_norm = _normalizar_uc_cache(uc)
        pattern = f"{CACHE_PREFIX}{uc_norm}:*"

        cursor = 0
        total = 0

        while True:
            cursor, keys = await redis_client.scan(
                cursor=cursor,
                match=pattern,
                count=200,
            )

            if keys:
                await redis_client.delete(*keys)
                total += len(keys)

            if cursor == 0:
                break

        logger.info(
            "Cache Redis invalidada para UC=%s (%d chaves)",
            uc_norm,
            total,
        )

    except Exception as exc:
        logger.warning(
            "Erro ao invalidar cache Redis da UC %s: %s | detalhe=%s",
            uc,
            type(exc).__name__,
            str(exc)[:300],
        )