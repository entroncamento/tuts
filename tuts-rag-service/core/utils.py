import re
import numpy as np
import unicodedata

def limpar_nome_uc(uc: str) -> str:
    sem_acentos = unicodedata.normalize("NFKD", uc)
    sem_acentos = sem_acentos.encode("ascii", "ignore").decode("ascii")
    # 🔥 CORREÇÃO 3: Substitui os espaços por underscores para proteger o Redis e os URLs
    return "".join(x for x in sem_acentos if x.isalnum() or x in " _-").strip().replace(" ", "_")

def sanitizar_input(texto: str) -> str:
    return re.sub(r"</?pergunta_aluno>", "", texto).strip()

def cosine_similarity(v1: list[float], v2: list[float]) -> float:
    a, b = np.asarray(v1, dtype=np.float32), np.asarray(v2, dtype=np.float32)
    denom = np.linalg.norm(a) * np.linalg.norm(b)
    return float(np.dot(a, b) / denom) if denom > 0 else 0.0