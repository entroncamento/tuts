import json
import uuid
import asyncio
import numpy as np
import redis.asyncio as redis
from collections import defaultdict
from langchain_community.vectorstores import FAISS
from langchain_community.retrievers import BM25Retriever
from redis.commands.search.field import TagField, VectorField, TextField
from redis.commands.search.index_definition import IndexDefinition, IndexType
from redis.commands.search.query import Query

from config import settings, logger

# ---------------------------------------------------------------------------
# Caches clássicas em RAM — FAISS, BM25 e documentos
# ---------------------------------------------------------------------------

faiss_cache: dict[str, FAISS] = {}
bm25_cache: dict[str, BM25Retriever] = {}
docs_cache: dict[str, list] = {}

_cache_locks: dict[str, asyncio.Lock] = defaultdict(asyncio.Lock)
_ingestao_locks: dict[str, asyncio.Lock] = defaultdict(asyncio.Lock)

# ---------------------------------------------------------------------------
# Redis — cache semântico persistente com vector search
# ---------------------------------------------------------------------------

# decode_responses=False é obrigatório porque os vectores são guardados em bytes puros
redis_client = redis.Redis(
    host=settings.redis_host,
    port=settings.redis_port,
    decode_responses=False,
)

# Dimensão do modelo paraphrase-multilingual-MiniLM-L12-v2
EMBEDDING_DIM = 384
INDEX_NAME    = "idx:semantic_cache"


async def init_redis_index() -> None:
    """Cria o índice vetorial no Redis, se ainda não existir."""
    try:
        await redis_client.ft(INDEX_NAME).info()
        logger.info("Índice Redis já existe — a saltar criação.")
    except Exception:
        logger.info("A criar índice vetorial no Redis para cache semântica...")
        schema = (
            TagField("uc"),
            TextField("resposta_json"),
            VectorField("embedding", "FLAT", {
                "TYPE":            "FLOAT32",
                "DIM":             EMBEDDING_DIM,
                "DISTANCE_METRIC": "COSINE",
            }),
        )
        definition = IndexDefinition(prefix=["cache:"], index_type=IndexType.HASH)
        await redis_client.ft(INDEX_NAME).create_index(fields=schema, definition=definition)
        logger.info("Índice vetorial criado com sucesso.")


async def procurar_cache_redis(uc: str, query_emb: list[float], threshold: float) -> dict | None:
    """
    Procura no Redis a resposta mais semanticamente parecida com a query.
    Devolve a resposta se a semelhança for superior ao threshold, caso contrário None.
    """
    try:
        emb_bytes = np.array(query_emb, dtype=np.float32).tobytes()

        # Redis usa distância coseno (0 = idêntico, 2 = oposto).
        # Semelhança 0.92 → distância máxima 0.08
        max_dist = 1.0 - threshold

        q = (
            Query(f"(@uc:{{{uc}}})=>[KNN 1 @embedding $vec AS distance]")
            .sort_by("distance")
            .return_fields("resposta_json", "distance")
            .dialect(2)
        )

        res = await redis_client.ft(INDEX_NAME).search(q, query_params={"vec": emb_bytes})

        if res.docs:
            dist = float(res.docs[0].distance)
            if dist <= max_dist:
                logger.info("Cache hit no Redis. Distância coseno: %.3f", dist)
                return json.loads(res.docs[0].resposta_json)

    except Exception as exc:
        logger.error("Erro ao ler cache do Redis: %s", exc)

    return None


async def guardar_cache_redis(uc: str, emb: list[float], resposta: dict, ttl_dias: int = 7) -> None:
    """Guarda a resposta no Redis com TTL configurável."""
    try:
        key       = f"cache:{uc}:{uuid.uuid4().hex}"
        emb_bytes = np.array(emb, dtype=np.float32).tobytes()

        await redis_client.hset(key, mapping={
            "uc":           uc,
            "embedding":    emb_bytes,
            "resposta_json": json.dumps(resposta, ensure_ascii=False),
        })
        await redis_client.expire(key, ttl_dias * 24 * 60 * 60)

    except Exception as exc:
        logger.error("Erro ao guardar cache no Redis: %s", exc)