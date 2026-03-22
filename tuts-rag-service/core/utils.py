import re
import hashlib
import numpy as np
import unicodedata

def limpar_nome_uc(uc: str) -> str:
    sem_acentos = unicodedata.normalize("NFKD", uc)
    sem_acentos = sem_acentos.encode("ascii", "ignore").decode("ascii")
    return "".join(x for x in sem_acentos if x.isalnum() or x in " _-").strip()

def sanitizar_input(texto: str) -> str:
    return re.sub(r"</?pergunta_aluno>", "", texto).strip()

def _normalizar_query(texto: str) -> str:
    texto = texto.lower().strip()
    texto = re.sub(r"[?!.]+$", "", texto)
    return re.sub(r"\s+", " ", texto)

def chave_cache_resposta(uc: str, query: str) -> str:
    query_normalizada = _normalizar_query(query)
    return hashlib.sha256(f"{uc}|{query_normalizada}".encode()).hexdigest()

def cosine_similarity(v1: list[float], v2: list[float]) -> float:
    a, b = np.asarray(v1, dtype=np.float32), np.asarray(v2, dtype=np.float32)
    denom = np.linalg.norm(a) * np.linalg.norm(b)
    return float(np.dot(a, b) / denom) if denom > 0 else 0.0