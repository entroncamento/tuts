import sqlite3
from config import logger

def init_db(db_path: str) -> None:
    con = sqlite3.connect(db_path)
    con.execute("""
        CREATE TABLE IF NOT EXISTS interacoes (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            ts              DATETIME DEFAULT (datetime('now')),
            uc              TEXT,
            thread_id       TEXT,
            query_original  TEXT,
            query_expandida TEXT,
            contexto        TEXT,
            resposta        TEXT,
            score_max       REAL,
            time_retrieval  REAL,
            time_rerank     REAL,
            time_llm        REAL,
            cache_hit       BOOLEAN
        )
    """)
    con.commit()
    con.close()

def registar_interacao(
    db_path: str, uc: str, thread_id: str, query_original: str,
    query_expandida: str, contexto: str, resposta: str, score_max: float,
    t_retrieval: float, t_rerank: float, t_llm: float, cache_hit: bool
) -> None:
    try:
        con = sqlite3.connect(db_path)
        con.execute(
            """INSERT INTO interacoes
               (uc, thread_id, query_original, query_expandida, contexto, resposta,
                score_max, time_retrieval, time_rerank, time_llm, cache_hit)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
            (uc, thread_id, query_original, query_expandida, contexto, resposta,
             score_max, t_retrieval, t_rerank, t_llm, cache_hit),
        )
        con.commit()
        con.close()
    except Exception as exc:
        logger.warning("Falha ao registar interação no SQLite: %s", exc)