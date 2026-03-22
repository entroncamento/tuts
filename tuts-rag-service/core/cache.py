import asyncio
from collections import defaultdict, deque
from cachetools import TTLCache
from langchain_community.vectorstores import FAISS
from langchain_community.retrievers import BM25Retriever
from config import settings

faiss_cache: dict[str, FAISS] = {}
bm25_cache: dict[str, BM25Retriever] = {}
docs_cache: dict[str, list] = {}

_cache_locks: dict[str, asyncio.Lock] = defaultdict(asyncio.Lock)
_ingestao_locks: dict[str, asyncio.Lock] = defaultdict(asyncio.Lock)

semantic_cache: dict[str, deque] = defaultdict(
    lambda: deque(maxlen=settings.semantic_cache_maxsize)
)

resposta_cache: TTLCache = TTLCache(
    maxsize=settings.resposta_cache_size,
    ttl=settings.resposta_cache_ttl,
)