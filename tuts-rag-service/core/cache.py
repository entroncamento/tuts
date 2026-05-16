import json
import os
import re
import uuid
import asyncio
from collections import defaultdict

import numpy as np
import redis.asyncio as redis
from langchain_community.retrievers import BM25Retriever
from langchain_community.vectorstores import FAISS
from redis.commands.search.field import TagField, TextField, VectorField
from redis.commands.search.index_definition import IndexDefinition, IndexType
from redis.commands.search.query import Query

from config import settings, logger, MANIFEST_FILE
from core.utils import limpar_nome_uc

faiss_cache: dict[str, FAISS] = {}
bm25_cache: dict[str, BM25Retriever] = {}
docs_cache: dict[str, list] = {}

_cache_locks: dict[str, asyncio.Lock] = defaultdict(asyncio.Lock)
_ingestao_locks: dict[str, asyncio.Lock] = defaultdict(asyncio.Lock)

# ── SEGURANÇA E AUTENTICAÇÃO REDIS ───────────────────────────────────────────
redis_client = redis.Redis(
    host=settings.redis_host,
    port=settings.redis_port,
    password=getattr(settings, "redis_password", None),
    db=getattr(settings, "redis_db", 0),
    ssl=getattr(settings, "redis_ssl", False),
    decode_responses=False,
)

# A dimensão deve acompanhar o modelo de embeddings (MiniLM = 384)
EMBEDDING_DIM = getattr(settings, "embedding_dim", 384) 
INDEX_NAME = "idx:semantic_cache_v2"
CACHE_PREFIX = "cache_v2:"


async def fechar_conexoes():
    """Para ser chamado no lifespan do main.py e garantir um graceful shutdown."""
    await redis_client.aclose()


def _normalizar_uc_cache(uc: str) -> str:
    return limpar_nome_uc(uc or "")


def _normalizar_versao_cache(versao: str) -> str:
    bruto = (versao or "sem_versao").strip().lower()
    bruto = re.sub(r"[^a-z0-9_]", "_", bruto)
    bruto = re.sub(r"_+", "_", bruto).strip("_")
    return bruto or "sem_versao"


def _escape_tag_value(valor: str) -> str:
    """
    Escapa caracteres sensíveis para TAG query do RediSearch.
    """
    return re.sub(r'([\\{}\\[\\]\\(\\)\\-:"\'@~&|!<>])', r'\\\1', valor)


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
        logger.warning("Falha a ler manifest da UC %s: %s", uc_norm, type(exc).__name__)
        return "sem_versao"


async def init_redis_index() -> None:
    try:
        await redis_client.ft(INDEX_NAME).info()
        logger.info("Índice Redis já existe — a saltar criação.")
    except Exception as e:
        # Só criamos se o erro for porque o índice não existe
        if "Unknown Index name" in str(e):
            logger.info("A criar índice vetorial no Redis para cache semântica...")
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
            definition = IndexDefinition(prefix=[CACHE_PREFIX], index_type=IndexType.HASH)
            await redis_client.ft(INDEX_NAME).create_index(fields=schema, definition=definition)
            logger.info("Índice vetorial criado com sucesso.")
        else:
            logger.error("Erro inesperado ao verificar o índice Redis: %s", type(e).__name__)


async def procurar_cache_redis(
    uc: str,
    versao_uc: str,
    query_emb: list[float],
    threshold: float,
) -> dict | None:
    try:
        uc_norm = _normalizar_uc_cache(uc)
        versao_norm = _normalizar_versao_cache(versao_uc)

        uc_tag = _escape_tag_value(uc_norm)
        versao_tag = _escape_tag_value(versao_norm)

        emb_bytes = np.array(query_emb, dtype=np.float32).tobytes()
        max_dist = 1.0 - threshold

        query_text = (
            f"(@uc:{{{uc_tag}}} @versao_uc:{{{versao_tag}}})"
            f"=>[KNN 1 @embedding $vec AS distance]"
        )

        q = (
            Query(query_text)
            .sort_by("distance")
            .return_fields("resposta_json", "distance")
            .dialect(2)
        )

        res = await redis_client.ft(INDEX_NAME).search(q, query_params={"vec": emb_bytes})

        if res.docs:
            dist = float(res.docs[0].distance)
            if dist <= max_dist:
                logger.info("Cache hit no Redis. UC=%s versão=%s dist=%.3f", uc_norm, versao_norm, dist)
                return json.loads(res.docs[0].resposta_json)

    except Exception as exc:
        logger.error("Erro ao ler cache do Redis | uc=%s | erro=%s", uc, type(exc).__name__)

    return None


async def guardar_cache_redis(
    uc: str,
    versao_uc: str,
    emb: list[float],
    resposta: dict,
    ttl_dias: int = 7,
) -> None:
    try:
        # ── SANITIZAÇÃO DE DADOS (PRIVACIDADE E FUGAS) ─────────────────────────
        # Extraímos APENAS as partes pedagógicas e genéricas da resposta.
        # Removemos intencionalmente "pergunta_original" e "query_expandida" para
        # evitar que um futuro "Cache Hit" entregue dados pessoais de um utilizador a outro.
        resposta_segura = {
            "resposta_stu": resposta.get("resposta_stu"),
            "sem_contexto": resposta.get("sem_contexto"),
            "fontes_consultadas": resposta.get("fontes_consultadas", []),
        }

        # Preservamos o calendário se houver intenções propostas
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
        await redis_client.expire(key, ttl_dias * 24 * 60 * 60)

    except Exception as exc:
        logger.error("Erro ao guardar cache no Redis: %s", type(exc).__name__)


async def invalidar_cache_redis_uc(uc: str) -> None:
    try:
        uc_norm = _normalizar_uc_cache(uc)
        pattern = f"{CACHE_PREFIX}{uc_norm}:*"
        cursor = 0
        total = 0

        while True:
            cursor, keys = await redis_client.scan(cursor=cursor, match=pattern, count=200)
            if keys:
                await redis_client.delete(*keys)
                total += len(keys)
            if cursor == 0:
                break

        logger.info("Cache Redis invalidada para UC=%s (%d chaves)", uc_norm, total)
    except Exception as exc:
        logger.error("Erro ao invalidar cache Redis da UC %s: %s", uc, type(exc).__name__)