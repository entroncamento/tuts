import re
import unicodedata
import logging
from pathlib import Path

# Reutilizar o logger configurado do sistema
logger = logging.getLogger("tuts")


def limpar_nome_uc(uc: str) -> str:
    """
    Normaliza o nome da UC para um formato seguro no sistema de ficheiros.
    Remove acentos, carateres especiais e limita o tamanho rigidamente.
    """
    sem_acentos = unicodedata.normalize("NFKD", uc)
    sem_acentos = sem_acentos.encode("ascii", "ignore").decode("ascii")
    limpo = "".join(x if x.isalnum() or x == "_" else "_" for x in sem_acentos)
    
    # Limite de 80 caracteres para prevenir erros no File System (Path Too Long)
    return re.sub(r"_+", "_", limpo).strip("_").lower()[:80]


def _base_faiss_dir() -> Path:
    from config import settings

    return Path(settings.base_faiss_dir)


def pasta_faiss_canonica_uc(uc: str) -> Path:
    """
    Caminho canonico para novas pastas FAISS.

    Novas ingestoes devem escrever sempre nesta pasta. Pastas antigas podem
    continuar a ser lidas atraves de resolver_pasta_faiss_uc().
    """
    return _base_faiss_dir() / limpar_nome_uc(uc)


def resolver_pasta_faiss_uc(uc: str) -> Path:
    """
    Resolve a pasta FAISS de uma UC com compatibilidade para nomes legacy.

    A ordem e:
    1. pasta canonica;
    2. primeira pasta existente em faiss_db cujo nome normalizado seja igual;
    3. caminho canonico, mesmo que ainda nao exista.

    Esta funcao nunca renomeia nem apaga pastas.
    """
    nome_normalizado = limpar_nome_uc(uc)
    caminho_canonico = _base_faiss_dir() / nome_normalizado

    if caminho_canonico.exists():
        return caminho_canonico

    base = _base_faiss_dir()
    if base.exists():
        for pasta in sorted(base.iterdir(), key=lambda p: p.name.lower()):
            if pasta.is_dir() and limpar_nome_uc(pasta.name) == nome_normalizado:
                logger.warning(
                    "A usar pasta FAISS legacy para UC '%s': %s",
                    uc,
                    pasta,
                )
                return pasta

    return caminho_canonico


def sanitizar_input(texto: str, max_chars: int = 4000) -> str:
    """
    Prepara e limpa o texto inserido pelo aluno para prevenir injeções básicas
    e limitar o consumo de recursos.
    """
    # Remove Null Bytes que podem quebrar funções nativas de C no Python ou SQL
    texto = (texto or "").replace("\x00", "") 
    
    # Remove as tags XML que usamos internamente para isolar a prompt no RAG
    texto = re.sub(r"</?pergunta_aluno>", "", texto, flags=re.IGNORECASE)
    texto = texto.strip()
    
    # Truncamento de segurança para evitar Prompt Stuffing e DoS de Tokens
    return texto[:max_chars]


def cosine_similarity(v1: list[float], v2: list[float]) -> float:
    """
    Calcula a similaridade de cosseno de forma segura.
    Valida as dimensões dos vetores antes de calcular para evitar exceções matemáticas.
    """
    import numpy as np

    a, b = np.asarray(v1, dtype=np.float32), np.asarray(v2, dtype=np.float32)
    
    # Prevenção de ValueError no dot product
    if a.shape != b.shape:
        logger.warning(
            "[UTILS] Erro Matemático Prevenido: Vetores com dimensões incompatíveis (%s vs %s)",
            a.shape,
            b.shape
        )
        return 0.0
        
    denom = np.linalg.norm(a) * np.linalg.norm(b)
    return float(np.dot(a, b) / denom) if denom > 0 else 0.0
