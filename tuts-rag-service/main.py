from contextlib import asynccontextmanager
from collections import defaultdict, deque
from concurrent.futures import ThreadPoolExecutor
from functools import partial
from typing import List

import asyncio
import aiofiles
import copy
import hashlib
import httpx
import json
import logging
import magic
import math
import numpy as np
import os
import re
import shutil
import sqlite3
import tempfile
import unicodedata
import uuid
import time
import datetime
from enum import Enum

import easyocr

from cachetools import TTLCache

from fastapi import Depends, FastAPI, File, Form, HTTPException, Request, UploadFile
from fastapi.security import APIKeyHeader
from pydantic_settings import BaseSettings, SettingsConfigDict

from slowapi import Limiter, _rate_limit_exceeded_handler
from slowapi.errors import RateLimitExceeded
from slowapi.util import get_remote_address

from langchain_community.vectorstores import FAISS
from langchain_huggingface import HuggingFaceEmbeddings
from langchain_community.document_loaders import PyMuPDFLoader
from langchain_text_splitters import RecursiveCharacterTextSplitter
from langchain_community.retrievers import BM25Retriever
from sentence_transformers import CrossEncoder

from tenacity import retry, stop_after_attempt, wait_exponential, RetryError

from dotenv import load_dotenv

from calendar_service import obter_servico_calendario

load_dotenv()


# ═══════════════════════════════════════════════════════════════════════════════
# Logging estruturado
# ═══════════════════════════════════════════════════════════════════════════════
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s | %(levelname)s | %(name)s | %(message)s",
    datefmt="%Y-%m-%dT%H:%M:%S",
)
logger = logging.getLogger("tuts")


# ═══════════════════════════════════════════════════════════════════════════════
# Configuração centralizada
# ═══════════════════════════════════════════════════════════════════════════════
class Settings(BaseSettings):
    iaedu_api_key:    str
    iaedu_agent_id:   str
    iaedu_channel_id: str
    professor_api_key: str

    uc_json_path: str = "database/data/cadeiras_mtc.json"

    faiss_k:      int   = 8
    bm25_k:       int   = 6
    final_k:      int   = 3
    score_minimo: float = -10.0

    max_image_mb: int = 4

    chunk_size:    int = 1200
    chunk_overlap: int = 250
    max_upload_mb: int = 50

    resposta_cache_ttl:  int = 300
    resposta_cache_size: int = 512

    semantic_cache_threshold: float = 0.92
    semantic_cache_maxsize:   int   = 100  # ✅ FIX #7: Configurável via .env

    embedding_model: str = "paraphrase-multilingual-MiniLM-L12-v2"
    reranker_model:  str = "cross-encoder/mmarco-mMiniLMv2-L12-H384-v1"

    rrf_k: int = 60

    base_faiss_dir: str = "faiss_db"
    sqlite_db:      str = "tuts_logs.db"

    model_config = SettingsConfigDict(env_file=".env", extra="ignore")


try:
    settings = Settings()
except Exception as exc:
    raise RuntimeError(f"CRITICAL: Configuração inválida — {exc}") from exc


# ═══════════════════════════════════════════════════════════════════════════════
# UCs válidas — carregadas do JSON do curso
# ═══════════════════════════════════════════════════════════════════════════════
def _carregar_ucs(path: str) -> type:
    try:
        with open(path, encoding="utf-8") as f:
            dados = json.load(f)
        membros = {entry["nome_uc"]: entry["nome_uc"] for entry in dados}
        logger.info("UCs carregadas do JSON: %d UCs encontradas.", len(membros))
        return Enum("UCEnum", membros)
    except FileNotFoundError:
        raise RuntimeError(f"CRITICAL: Ficheiro de UCs não encontrado em '{path}'")
    except Exception as exc:
        raise RuntimeError(f"CRITICAL: Erro ao carregar UCs — {exc}") from exc

UCEnum = _carregar_ucs(settings.uc_json_path)


# ═══════════════════════════════════════════════════════════════════════════════
# ✅ FIX #11: Enum para preferência — FastAPI rejeita valores inválidos com 422
# ═══════════════════════════════════════════════════════════════════════════════
class PreferenciaEnum(str, Enum):
    textual = "textual"
    visual  = "visual"
    plano   = "plano"
    quiz    = "quiz"
    feynman = "feynman"


# ═══════════════════════════════════════════════════════════════════════════════
# Autenticação — apenas para rotas de professores
# ═══════════════════════════════════════════════════════════════════════════════
api_key_header = APIKeyHeader(name="X-API-Key", auto_error=True)

async def exigir_professor(chave: str = Depends(api_key_header)) -> None:
    if chave.strip() != settings.professor_api_key.strip():
        raise HTTPException(status_code=403, detail="Acesso reservado a professores.")


# ═══════════════════════════════════════════════════════════════════════════════
# Rate limiting
# ═══════════════════════════════════════════════════════════════════════════════
limiter = Limiter(key_func=get_remote_address)


# ═══════════════════════════════════════════════════════════════════════════════
# SQLite — registo de interações
# ═══════════════════════════════════════════════════════════════════════════════
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


# ═══════════════════════════════════════════════════════════════════════════════
# ✅ FIX #2: Callback para logar erros de tarefas fire-and-forget
# ═══════════════════════════════════════════════════════════════════════════════
def _cb_log_erro(task: asyncio.Future, nome: str) -> None:
    """Callback para tarefas background — regista exceções que seriam perdidas."""
    try:
        exc = task.exception()
        if exc:
            logger.warning("Tarefa background '%s' falhou: %s", nome, exc)
    except asyncio.CancelledError:
        pass


def _disparar_background(coro_or_future: asyncio.Future, nome: str) -> None:
    """Regista um Future/Task e anexa o callback de erro."""
    task = asyncio.ensure_future(coro_or_future)
    task.add_done_callback(lambda t: _cb_log_erro(t, nome))


# ═══════════════════════════════════════════════════════════════════════════════
# Lifespan
# ═══════════════════════════════════════════════════════════════════════════════
@asynccontextmanager
async def lifespan(app: FastAPI):
    init_db(settings.sqlite_db)
    app.state.http_client = httpx.AsyncClient()
    logger.info("HTTP client e base de dados prontos.")
    yield
    await app.state.http_client.aclose()
    executor.shutdown(wait=True)
    logger.info("Recursos libertados. Servidor encerrado.")


# ═══════════════════════════════════════════════════════════════════════════════
# App
# ═══════════════════════════════════════════════════════════════════════════════
app = FastAPI(
    title="TUT's RAG API",
    lifespan=lifespan,
    openapi_tags=[
        {"name": "Alunos",      "description": "Endpoints para alunos — sem autenticação."},
        {"name": "Professores", "description": "Endpoints para professores — requerem X-API-Key."},
        {"name": "Sistema",     "description": "Monitorização e saúde do servidor."},
    ],
)
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)


# ═══════════════════════════════════════════════════════════════════════════════
# Modelos de ML
# ═══════════════════════════════════════════════════════════════════════════════
logger.info("A carregar embeddings...")
embeddings_model = HuggingFaceEmbeddings(model_name=settings.embedding_model)

logger.info("A carregar motor OCR...")
leitor_ocr = easyocr.Reader(["pt"])

logger.info("A carregar modelo de Re-Ranking...")
reranker = CrossEncoder(settings.reranker_model)

logger.info("Todos os modelos prontos.")

_cpu_count = os.cpu_count() or 1
executor = ThreadPoolExecutor(max_workers=min(32, _cpu_count + 4))
logger.info("ThreadPoolExecutor iniciado com max_workers=%d.", min(32, _cpu_count + 4))


# ═══════════════════════════════════════════════════════════════════════════════
# Cache em memória
# ═══════════════════════════════════════════════════════════════════════════════
faiss_cache:     dict[str, FAISS]         = {}
bm25_cache:      dict[str, BM25Retriever] = {}
docs_cache:      dict[str, list]          = {}
_cache_locks:    dict[str, asyncio.Lock]  = defaultdict(asyncio.Lock)
_ingestao_locks: dict[str, asyncio.Lock]  = defaultdict(asyncio.Lock)

# ✅ FIX #7: deque com maxlen — O(1) automático, sem pop(0) manual
semantic_cache: dict[str, deque] = defaultdict(
    lambda: deque(maxlen=settings.semantic_cache_maxsize)
)

resposta_cache: TTLCache = TTLCache(
    maxsize=settings.resposta_cache_size,
    ttl=settings.resposta_cache_ttl,
)


# ═══════════════════════════════════════════════════════════════════════════════
# Utilitários
# ═══════════════════════════════════════════════════════════════════════════════
def _limpar_nome_uc(uc: str) -> str:
    sem_acentos = unicodedata.normalize("NFKD", uc)
    sem_acentos = sem_acentos.encode("ascii", "ignore").decode("ascii")
    return "".join(x for x in sem_acentos if x.isalnum() or x in " _-").strip()

def _sanitizar_input(texto: str) -> str:
    return re.sub(r"</?pergunta_aluno>", "", texto).strip()

def _normalizar_query(texto: str) -> str:
    texto = texto.lower().strip()
    texto = re.sub(r"[?!.]+$", "", texto)
    texto = re.sub(r"\s+", " ", texto)
    return texto

def _chave_cache_resposta(uc: str, query: str) -> str:
    query_normalizada = _normalizar_query(query)
    return hashlib.sha256(f"{uc}|{query_normalizada}".encode()).hexdigest()

# ✅ FIX #9: Cosine similarity vetorizada com NumPy (~10× mais rápida)
def _cosine_similarity(v1: list[float], v2: list[float]) -> float:
    a, b = np.asarray(v1, dtype=np.float32), np.asarray(v2, dtype=np.float32)
    denom = np.linalg.norm(a) * np.linalg.norm(b)
    return float(np.dot(a, b) / denom) if denom > 0 else 0.0


# ═══════════════════════════════════════════════════════════════════════════════
# Detecção de perguntas de resumo/visão geral
# ═══════════════════════════════════════════════════════════════════════════════
_PADROES_RESUMO = re.compile(
    r"(qual|quais).{0,20}(mat[eé]ria|conte[uú]do|assunto|t[oó]pico|tema)"
    r"|o que.{0,15}(pdf|documento|ficheiro|est[aá] nos)"
    r"|(resume|resumo|s[íi]ntese|sum[aá]rio|visão geral|overview|explica tudo"
    r"|do que trata|fala sobre o qu[eê]|do que [eé]|do que se trata)",
    re.IGNORECASE,
)

def _e_pergunta_de_resumo(texto: str) -> bool:
    return bool(_PADROES_RESUMO.search(texto))

def _query_resumo_para_uc(uc_nome: str) -> str:
    return (
        f"introdução conceitos fundamentais temas principais conteúdos "
        f"teoria definição {uc_nome}"
    )


# ═══════════════════════════════════════════════════════════════════════════════
# RRF — Reciprocal Rank Fusion
# ═══════════════════════════════════════════════════════════════════════════════
def _rrf_fusion(listas: list[list], k: int = 60) -> list:
    scores:    dict[str, float]  = defaultdict(float)
    doc_index: dict[str, object] = {}
    for lista in listas:
        for rank, doc in enumerate(lista):
            chave = doc.page_content
            scores[chave]    += 1.0 / (k + rank)
            doc_index[chave]  = doc
    ordenados = sorted(scores.items(), key=lambda x: x[1], reverse=True)
    return [doc_index[chave] for chave, _ in ordenados]


# ═══════════════════════════════════════════════════════════════════════════════
# Prompts
# ═══════════════════════════════════════════════════════════════════════════════
def _prompt_decomposicao(pergunta: str) -> str:
    return f"""Decompõe a seguinte pergunta complexa académica em 2 a 4 sub-perguntas precisas, focadas num único conceito cada uma, para melhorar a precisão de pesquisa num motor de busca documental.
Se a pergunta já for simples e de conceito único, devolve a mesma pergunta original.
Devolve APENAS as sub-perguntas, uma por linha, sem enumeração, pontos de interrogação ou introduções.

PERGUNTA ORIGINAL:
{pergunta}

SUB-PERGUNTAS:"""

def _prompt_reescrita(historico_json: str, pergunta: str) -> str:
    return f"""És um especialista em recuperação de informação académica.

A tua única tarefa é reescrever a última pergunta do aluno numa query de pesquisa \
autónoma e rica em palavras-chave, adequada para pesquisar num índice vectorial de \
documentos universitários.

REGRAS:
1. Resolve todas as referências anafóricas (ex: "ele", "isso", "esse conceito") \
usando o contexto do histórico.
2. Expande siglas e abreviaturas para os termos completos.
3. Adiciona sinónimos académicos relevantes se enriquecerem a pesquisa.
4. Se a pergunta for já clara e autónoma, devolve-a sem alterações.
5. Devolve APENAS a query reformulada — sem explicações, sem prefixos, sem aspas.

HISTÓRICO DA CONVERSA (últimas 3 trocas):
{historico_json}

ÚLTIMA PERGUNTA DO ALUNO:
{pergunta}

QUERY REFORMULADA:"""


# ✅ FIX #13: Separação da instrução de formato do prompt principal
_FORMATO_BASE = """
FORMATO DE RESPOSTA E CITAÇÕES:
- Usa Markdown para estruturar a resposta (títulos ##, listas, negrito para conceitos-chave).
- **CITA SEMPRE AS FONTES**: Cada afirmação ou bloco de informação deve terminar com a referência exata extraída do cabeçalho do contexto, no formato `[Ficheiro:Página]`. Exemplo: "O overfitting ocorre quando o modelo memoriza os dados de treino [Machine_Learning.pdf:14]."
- Não inventes citações. Usa apenas os nomes de ficheiros e páginas exatos fornecidos nas tags [CABEÇALHO FONTE: ...]."""

def _instrucao_formato(preferencia: PreferenciaEnum) -> str:
    """Devolve o bloco de instrução de formato para o modo pedido pelo aluno."""
    if preferencia == PreferenciaEnum.visual:
        return _FORMATO_BASE + """
- Sempre que o conteúdo o permitir, representa a resposta com um diagrama Mermaid.js.
- Envolve sempre o código do diagrama num bloco Markdown padrão (```mermaid).
- 🎨 ADICIONA CORES MODERNAS: Na linha imediatamente abaixo de ```mermaid, escreve OBRIGATORIAMENTE este código exato para estilizar o gráfico:
%%{init: {'theme': 'base', 'themeVariables': { 'primaryColor': '#3b82f6', 'primaryTextColor': '#ffffff', 'primaryBorderColor': '#1d4ed8', 'lineColor': '#9ca3af', 'secondaryColor': '#10b981', 'tertiaryColor': '#f59e0b'}}}%%
- A linha seguinte ao init tem de ser OBRIGATORIAMENTE o tipo do diagrama (ex: `mindmap` ou `flowchart TD`). NUNCA escrevas a palavra "mermaid" debaixo dos crases.
- REGRA DE OURO MERMAID: DENTRO do bloco de código do diagrama, NÃO incluas NENHUMA citação [Ficheiro:Página] nem uses parênteses retos "[" ou curvos "(". Usa apenas texto limpo e aspas nos nós para não quebrar a sintaxe.
- Coloca as citações [Ficheiro:Página] OBRIGATORIAMENTE no parágrafo de síntese em texto que deves escrever debaixo do diagrama."""

    if preferencia == PreferenciaEnum.plano:
        return _FORMATO_BASE + """
- MODO PLANO DE ESTUDO COM CALENDÁRIO: O aluno pediu um plano de estudo para um teste/exame.
- Analisa o tempo e divide os temas do Contexto em dias de estudo.
- Escreve um texto motivador para o aluno ler.
- NO FINAL DA TUA RESPOSTA, deves incluir OBRIGATORIAMENTE um bloco escondido para a nossa API criar os eventos no Google Calendar do aluno. Usa EXATAMENTE este formato:
[CALENDARIO]
1|Ler sobre o conceito X
2|Fazer exercícios práticos
3|Revisão final
[/CALENDARIO]
- O número antes da barra vertical ( | ) indica daqui a QUANTOS DIAS o evento vai acontecer (1 = amanhã, 2 = depois de amanhã, etc.)."""

    if preferencia == PreferenciaEnum.quiz:
        return _FORMATO_BASE + """
- MODO QUIZ: O aluno quer testar os seus conhecimentos.
- Lê o Contexto e gera 3 perguntas desafiantes (podem ser de escolha múltipla ou verdadeiro/falso) sobre a matéria.
- IMPORTANTE: NÃO DÊS AS RESPOSTAS! Pede ao aluno para responder à tua mensagem com as suas opções.
- Mantém um tom de desafio amigável (ex: "Vamos ver se percebeste bem esta matéria!")."""

    if preferencia == PreferenciaEnum.feynman:
        return _FORMATO_BASE + """
- MODO TÉCNICA DE FEYNMAN (O Leigo): O aluno quer aprender ENSINANDO-TE a ti.
- Assume a persona de um caloiro muito confuso ou de uma pessoa completamente leiga no assunto.
- Olha para o tema da pergunta e pede ao aluno para te explicar o conceito de forma muito simples, "como se tivesses 10 anos".
- NÃO EXPLIQUES A MATÉRIA! O teu objetivo é forçar o aluno a explicar.
- Usa frases do género: "Podes explicar-me isso por palavras tuas?", "Mas o que é que isso quer dizer na prática?", "Estou um bocado confuso, podes dar-me um exemplo do dia a dia?"."""

    # PreferenciaEnum.textual (default)
    return _FORMATO_BASE + """
- Para conceitos com múltiplas componentes, usa uma lista numerada.
- Para definições, apresenta primeiro o conceito em negrito, depois a explicação.
- Mantém um tom académico mas acessível."""


def _prompt_rag(
    uc: str,
    contexto: str,
    pergunta_original: str,
    preferencia: PreferenciaEnum,
    tem_imagem: bool,
    modo_resumo: bool = False,
) -> str:
    instrucao_formato = _instrucao_formato(preferencia)

    instrucao_imagem = ""
    if tem_imagem:
        instrucao_imagem = """
IMAGEM DO ALUNO:
O aluno enviou uma fotografia (apontamentos, exercício, enunciado). O texto extraído \
por OCR está incluído na pergunta sob a tag [TEXTO IMAGEM].
- Analisa o texto da imagem em conjunto com o Contexto dos PDFs.
- Se o texto da imagem contiver um exercício ou problema, resolve-o passo a passo \
usando apenas os conceitos presentes no Contexto.
- Se a imagem contradizer os PDFs, confia nos PDFs e menciona a discrepância."""

    instrucao_modo = ""
    if modo_resumo:
        instrucao_modo = """
MODO RESUMO GERAL:
O aluno está a pedir uma visão geral dos conteúdos da UC, não uma resposta a uma \
dúvida específica. Neste caso:
- Identifica os temas e conceitos principais presentes nos fragmentos do Contexto.
- Organiza-os em tópicos com títulos Markdown (##).
- Para cada tópico, escreve uma breve descrição do que é abordado e a(s) citação(ões) respectiva(s).
- Termina com um parágrafo de síntese que dê ao aluno uma ideia clara do âmbito da UC."""

    return f"""És o TUT'S, o assistente académico oficial da Universidade de Aveiro, \
especializado na Unidade Curricular de {uc}.

A tua missão é ajudar estudantes universitários a compreender os conteúdos da sua \
cadeira com rigor, clareza e profundidade pedagógica.

════════════════════════════════════════
RESTRIÇÕES ABSOLUTAS (nunca violar):
════════════════════════════════════════
1. GROUNDING ESTRITO — Responde EXCLUSIVAMENTE com base no Contexto fornecido abaixo. \
Não uses conhecimento externo, não inventas definições, não extrapolas para além do que \
os documentos dizem.
2. AUSÊNCIA DE INFORMAÇÃO — Se a resposta não estiver no Contexto, responde \
EXACTAMENTE com esta frase e nada mais:
   "Desculpa, mas não encontrei informação sobre esse tema nos documentos validados \
de {uc}. Sugiro que consultes o teu professor ou os slides da aula."
3. PROIBIÇÃO DE ALUCINAÇÃO — Nunca completes informação em falta com suposições. \
Se os documentos forem inconclusivos, diz explicitamente que a informação disponível \
é parcial.
4. FOCO NA UC — Mesmo que a pergunta pareça genérica, enquadra sempre a resposta \
no contexto específico de {uc}.
{instrucao_imagem}{instrucao_modo}
════════════════════════════════════════
PROCESSO DE RACIOCÍNIO (segue esta ordem):
════════════════════════════════════════
Passo 1 — Identifica quais os fragmentos do Contexto que são relevantes para a pergunta.
Passo 2 — Sintetiza a informação relevante, eliminando redundâncias.
Passo 3 — Estrutura a resposta de forma pedagógica (do geral para o específico).
Passo 4 — Adiciona as citações [Ficheiro:Página] no final de cada afirmação relevante baseada no cabeçalho do contexto.
Passo 5 — Verifica se a resposta responde inteiramente à pergunta. Se não, menciona \
o que ficou por cobrir.
{instrucao_formato}

════════════════════════════════════════
CONTEXTO — FRAGMENTOS DOS PDFs DA UC:
════════════════════════════════════════
{contexto}

════════════════════════════════════════
PERGUNTA DO ALUNO:
════════════════════════════════════════
<pergunta_aluno>
{pergunta_original}
</pergunta_aluno>

RESPOSTA (em Português Europeu):"""


# ═══════════════════════════════════════════════════════════════════════════════
# Acesso ao FAISS e BM25
# ═══════════════════════════════════════════════════════════════════════════════
async def get_vector_store(uc: str) -> FAISS | None:
    async with _cache_locks[uc]:
        if uc not in faiss_cache:
            db_path    = os.path.join(settings.base_faiss_dir, uc)
            index_path = os.path.join(db_path, "index.faiss")
            if os.path.exists(index_path):
                logger.info("A carregar FAISS do disco para UC '%s'.", uc)
                loop = asyncio.get_running_loop()
                faiss_cache[uc] = await loop.run_in_executor(
                    executor,
                    lambda: FAISS.load_local(
                        db_path, embeddings_model,
                        allow_dangerous_deserialization=True,
                    ),
                )
                if uc not in docs_cache:
                    docs_cache[uc] = list(faiss_cache[uc].docstore._dict.values())
                    logger.info(
                        "docs_cache populado para UC '%s' (%d docs).",
                        uc, len(docs_cache[uc]),
                    )
            else:
                return None
    return faiss_cache[uc]


async def get_bm25_retriever(uc: str) -> BM25Retriever:
    async with _cache_locks[uc]:
        if uc not in bm25_cache:
            if uc not in docs_cache or not docs_cache[uc]:
                raise RuntimeError(f"docs_cache vazio para UC '{uc}' — impossível construir BM25.")
            logger.info("A construir índice BM25 para UC '%s' (%d docs).", uc, len(docs_cache[uc]))
            retriever   = BM25Retriever.from_documents(docs_cache[uc])
            retriever.k = settings.bm25_k
            bm25_cache[uc] = retriever
    return bm25_cache[uc]


# ═══════════════════════════════════════════════════════════════════════════════
# Ingestão
# ═══════════════════════════════════════════════════════════════════════════════
def _build_index(
    temp_path: str, filename: str, uc: str, chunk_size: int, chunk_overlap: int
) -> tuple[int, list]:
    loader     = PyMuPDFLoader(temp_path)
    documentos = loader.load()

    for doc in documentos:
        pagina_humana = doc.metadata.get("page", 0) + 1
        doc.page_content = (
            f"[CABEÇALHO FONTE: {filename}:{pagina_humana}]\n{doc.page_content}"
        )

    text_splitter = RecursiveCharacterTextSplitter(
        chunk_size=chunk_size,
        chunk_overlap=chunk_overlap,
        separators=["\n\n", "\n", ". ", " ", ""],
    )
    chunks = text_splitter.split_documents(documentos)

    db_path    = os.path.join(settings.base_faiss_dir, uc)
    index_path = os.path.join(db_path, "index.faiss")
    os.makedirs(db_path, exist_ok=True)

    if os.path.exists(index_path):
        vs = FAISS.load_local(db_path, embeddings_model, allow_dangerous_deserialization=True)
        vs.add_documents(chunks)
    else:
        vs = FAISS.from_documents(chunks, embeddings_model)

    vs.save_local(db_path)
    logger.info("Índice FAISS guardado para UC '%s' (%d chunks).", uc, len(chunks))
    return len(chunks), chunks


# ═══════════════════════════════════════════════════════════════════════════════
# Chamada à API IAedu — com retry automático
# ═══════════════════════════════════════════════════════════════════════════════
@retry(
    stop=stop_after_attempt(3),
    wait=wait_exponential(multiplier=1, min=1, max=4),
    reraise=True,
)
async def chamar_iaedu(prompt: str, thread_id: str, request: Request) -> str:
    client = request.app.state.http_client

    url = (
        f"https://api.iaedu.pt/agent-chat/api/v1/agent"
        f"/{settings.iaedu_agent_id}/stream"
    )
    headers   = {"x-api-key": settings.iaedu_api_key}
    form_data = {
        "channel_id": settings.iaedu_channel_id,
        "thread_id":  thread_id,
        "user_info":  "{}",
        "message":    prompt,
    }

    resposta_api = await client.post(
        url, headers=headers, data=form_data, timeout=60.0
    )
    resposta_api.raise_for_status()

    for linha in resposta_api.text.split("\n\n"):
        if not linha.strip():
            continue
        try:
            dados = json.loads(linha)
            if (
                dados.get("type") == "message"
                and "content" in dados
                and "content" in dados["content"]
            ):
                return dados["content"]["content"]
        except json.JSONDecodeError:
            continue

    logger.warning("Resposta IAedu sem conteúdo reconhecível (thread: %s).", thread_id)
    return ""


# ═══════════════════════════════════════════════════════════════════════════════
# ROTA: /health
# ═══════════════════════════════════════════════════════════════════════════════
@app.get("/health", tags=["Sistema"])
async def health():
    return {
        "status":             "ok",
        "modelos_carregados": True,
        "ucs_em_cache":       list(faiss_cache.keys()),
    }


# ═══════════════════════════════════════════════════════════════════════════════
# ROTA: GET /ucs  (professores)
# ═══════════════════════════════════════════════════════════════════════════════
@app.get("/ucs", dependencies=[Depends(exigir_professor)], tags=["Professores"])
async def listar_ucs():
    base = settings.base_faiss_dir
    if not os.path.exists(base):
        return {"ucs": []}

    resultado = []
    for nome_uc in os.listdir(base):
        caminho = os.path.join(base, nome_uc)
        if os.path.isdir(caminho) and os.path.exists(os.path.join(caminho, "index.faiss")):
            vs           = await get_vector_store(nome_uc)
            total_chunks = len(docs_cache.get(nome_uc, [])) if vs else 0
            resultado.append({"uc": nome_uc, "chunks": total_chunks})

    return {"ucs": resultado}


# ═══════════════════════════════════════════════════════════════════════════════
# ROTA: DELETE /ucs/{uc}/conteudo  (professores)
# ═══════════════════════════════════════════════════════════════════════════════
@app.delete("/ucs/{uc}/conteudo", dependencies=[Depends(exigir_professor)], tags=["Professores"])
async def apagar_conteudo_uc(uc: str):
    uc_limpa = _limpar_nome_uc(uc)
    db_path  = os.path.join(settings.base_faiss_dir, uc_limpa)

    if not os.path.exists(db_path):
        raise HTTPException(
            status_code=404,
            detail=f"Não existem documentos indexados para a UC '{uc_limpa}'.",
        )

    shutil.rmtree(db_path)
    faiss_cache.pop(uc_limpa, None)
    bm25_cache.pop(uc_limpa, None)
    docs_cache.pop(uc_limpa, None)
    semantic_cache.pop(uc_limpa, None)

    logger.info("Conteúdo da UC '%s' removido pelo professor.", uc_limpa)
    return {
        "mensagem": (
            f"Conteúdo da UC '{uc_limpa}' removido com sucesso. "
            "A UC continua activa no sistema — podes carregar novos documentos a qualquer momento."
        ),
    }


# ═══════════════════════════════════════════════════════════════════════════════
# ROTA: POST /ingestao  (professores)
# ═══════════════════════════════════════════════════════════════════════════════
@app.post(
    "/ingestao",
    dependencies=[Depends(exigir_professor)],
    tags=["Professores"],
    openapi_extra={
        "requestBody": {
            "content": {
                "multipart/form-data": {
                    "schema": {
                        "type": "object",
                        "required": ["files", "uc"],
                        "properties": {
                            "files": {
                                "type":  "array",
                                "items": {"type": "string", "format": "binary"},
                                "description": "Um ou mais ficheiros PDF a indexar.",
                            },
                            "uc": {
                                "type":        "string",
                                "enum":        [e.value for e in UCEnum],
                                "description": "Nome da Unidade Curricular de destino.",
                            },
                            "chunk_size": {
                                "type":        "integer",
                                "description": "Tamanho de cada chunk (padrão: 1200).",
                            },
                            "chunk_overlap": {
                                "type":        "integer",
                                "description": "Sobreposição entre chunks (padrão: 250).",
                            },
                        },
                    }
                }
            }
        }
    },
)
async def ingestao(
    files:         List[UploadFile] = File(...),
    uc:            UCEnum           = Form(...),
    chunk_size:    int              = Form(None),
    chunk_overlap: int              = Form(None),
):
    max_bytes = settings.max_upload_mb * 1024 * 1024
    cs        = chunk_size    or settings.chunk_size
    co        = chunk_overlap or settings.chunk_overlap
    uc_limpa  = _limpar_nome_uc(uc.value)

    if not uc_limpa:
        raise HTTPException(status_code=400, detail="Nome de UC inválido.")

    conteudos = []
    for file in files:
        if not file.filename.lower().endswith(".pdf"):
            raise HTTPException(status_code=400, detail=f"'{file.filename}' não é um PDF válido.")

        conteudo = await file.read()

        if len(conteudo) > max_bytes:
            raise HTTPException(status_code=413, detail=f"'{file.filename}' excede o limite de {settings.max_upload_mb} MB.")

        tipo_mime = magic.from_buffer(conteudo, mime=True)
        if tipo_mime != "application/pdf":
            raise HTTPException(status_code=400, detail=f"'{file.filename}' não é um PDF válido (detetado: {tipo_mime}).")

        conteudos.append((file.filename, conteudo))

    resultados   = []
    novos_chunks = []
    loop = asyncio.get_running_loop()

    async with _ingestao_locks[uc_limpa]:
        for filename, conteudo in conteudos:
            # ✅ FIX #6: tempfile gerido pelo SO — garante limpeza mesmo em crashes
            tmp_fd, temp_path = tempfile.mkstemp(suffix=".pdf", prefix=f"tuts_{uc_limpa}_")
            try:
                os.close(tmp_fd)  # Fechar o file descriptor antes de escrever com aiofiles
                async with aiofiles.open(temp_path, "wb") as buf:
                    await buf.write(conteudo)

                total_chunks, chunks = await loop.run_in_executor(
                    executor, _build_index, temp_path, filename, uc_limpa, cs, co
                )
                novos_chunks.extend(chunks)

                logger.info("Ingestão: UC='%s' | ficheiro='%s' | chunks=%d", uc_limpa, filename, total_chunks)
                resultados.append({"ficheiro": filename, "total_chunks": total_chunks, "status": "sucesso"})

            except Exception as exc:
                logger.error("Erro ao indexar '%s': %s", filename, exc)
                resultados.append({"ficheiro": filename, "status": "erro", "detalhe": str(exc)})
            finally:
                if os.path.exists(temp_path):
                    os.remove(temp_path)

        faiss_cache.pop(uc_limpa, None)
        bm25_cache.pop(uc_limpa, None)
        semantic_cache.pop(uc_limpa, None)

        if novos_chunks:
            docs_cache.setdefault(uc_limpa, []).extend(novos_chunks)
            logger.info(
                "docs_cache actualizado para UC '%s': total=%d docs.",
                uc_limpa, len(docs_cache[uc_limpa]),
            )

    total_sucesso = sum(1 for r in resultados if r["status"] == "sucesso")
    total_erro    = len(resultados) - total_sucesso

    return {
        "mensagem":        f"{total_sucesso} ficheiro(s) indexado(s) com sucesso para '{uc_limpa}'. {total_erro} erro(s).",
        "uc":              uc_limpa,
        "chunk_size":      cs,
        "total_ficheiros": len(resultados),
        "resultados":      resultados,
    }


# ═══════════════════════════════════════════════════════════════════════════════
# Função auxiliar — criar evento no Google Calendar (síncrono, para executor)
# ═══════════════════════════════════════════════════════════════════════════════
def _criar_evento_google_sync(service, evento: dict) -> None:
    try:
        service.events().insert(calendarId="primary", body=evento).execute()
        logger.info("Evento do calendário inserido: %s", evento["summary"])
    except Exception as exc:
        logger.error("Erro ao inserir no calendário: %s", exc)


# ═══════════════════════════════════════════════════════════════════════════════
# ROTA: POST /perguntar  (alunos)
# ═══════════════════════════════════════════════════════════════════════════════
@app.post("/perguntar", tags=["Alunos"])
@limiter.limit("20/minute")
async def perguntar(
    request:     Request,
    texto:       str              = Form(...),
    thread_id:   str              = Form(...),
    uc:          UCEnum           = Form(...),
    preferencia: PreferenciaEnum  = Form(PreferenciaEnum.textual),  # ✅ FIX #11
    historico:   str              = Form("[]"),
    imagem:      UploadFile       = File(None),
):
    # ✅ FIX #4: Validar thread_id como UUID — previne injeção e enumeração
    try:
        uuid.UUID(str(thread_id))
    except ValueError:
        raise HTTPException(status_code=400, detail="thread_id inválido. Tem de ser um UUID válido.")

    t_start_total = time.perf_counter()
    t_retrieval, t_rerank, t_llm = 0.0, 0.0, 0.0

    loop     = asyncio.get_running_loop()
    uc_limpa = _limpar_nome_uc(uc.value)
    uc_nome  = uc.value

    vs = await get_vector_store(uc_limpa)
    if vs is None:
        return {
            "status":   "erro",
            "mensagem": (
                f"Ainda não existem documentos carregados para a UC: {uc_nome}. "
                "Fala com o teu professor!"
            ),
        }

    texto_final_aluno = _sanitizar_input(texto)
    tem_imagem        = False

    # ── OCR ──────────────────────────────────────────────────────────────────
    if imagem:
        max_img_bytes = settings.max_image_mb * 1024 * 1024
        try:
            conteudo_img = await imagem.read()
            if len(conteudo_img) > max_img_bytes:
                logger.warning(
                    "Imagem rejeitada: %.1f MB > limite de %d MB.",
                    len(conteudo_img) / 1024 / 1024,
                    settings.max_image_mb,
                )
            else:
                funcao_ocr     = partial(leitor_ocr.readtext, conteudo_img, detail=0)
                resultados_ocr = await loop.run_in_executor(executor, funcao_ocr)
                texto_extraido = "\n".join(resultados_ocr)
                if texto_extraido.strip():
                    texto_final_aluno += f"\n\n[TEXTO IMAGEM]:\n{texto_extraido}"
                    tem_imagem = True
                    logger.info("OCR extraiu %d caracteres.", len(texto_extraido))
        except Exception as exc:
            logger.warning("Erro OCR (ignorado): %s", exc)

    # ── Semantic Cache check ─────────────────────────────────────────────────
    query_emb = await loop.run_in_executor(executor, embeddings_model.embed_query, texto_final_aluno)

    for entrada in semantic_cache[uc_limpa]:
        similaridade = _cosine_similarity(query_emb, entrada["emb"])
        if similaridade > settings.semantic_cache_threshold:
            logger.info("SEMANTIC CACHE HIT (sim=%.3f) para UC='%s'", similaridade, uc_limpa)
            # ✅ FIX #2: disparar registo com callback de erro
            _disparar_background(
                loop.run_in_executor(
                    executor, registar_interacao,
                    settings.sqlite_db, uc_limpa, thread_id,
                    texto_final_aluno, "cache_hit_semantic", "cache",
                    entrada["resposta"]["resposta_stu"], 1.0, 0.0, 0.0, 0.0, True,
                ),
                "registar_interacao_cache_hit",
            )
            # ✅ FIX #1: deepcopy para não expor referência mutável
            return copy.deepcopy(entrada["resposta"])

    # ── Histórico ────────────────────────────────────────────────────────────
    try:
        mensagens_historico = json.loads(historico)
    except (json.JSONDecodeError, ValueError):
        mensagens_historico = []

    modo_resumo      = _e_pergunta_de_resumo(texto_final_aluno)
    queries_pesquisa = [texto_final_aluno]

    if modo_resumo:
        queries_pesquisa = [_query_resumo_para_uc(uc_nome)]
        logger.info("Modo resumo detectado. Query: '%s'", queries_pesquisa[0])

    elif mensagens_historico:
        historico_json = json.dumps(mensagens_historico[-3:], ensure_ascii=False, indent=2)
        try:
            query_reescrita = await chamar_iaedu(
                _prompt_reescrita(historico_json, texto_final_aluno),
                thread_id,
                request,
            )
            if query_reescrita.strip():
                logger.info("Query reescrita: '%s' → '%s'", texto_final_aluno[:80], query_reescrita[:80])
                queries_pesquisa = [query_reescrita]
        except (RetryError, Exception) as exc:
            logger.warning("Falha na reescrita da query, a usar original: %s", exc)

    elif not tem_imagem:
        try:
            decomp_response = await chamar_iaedu(
                _prompt_decomposicao(texto_final_aluno),
                thread_id,
                request,
            )
            subqueries = [sq.strip() for sq in decomp_response.split("\n") if sq.strip() and len(sq) > 5]
            if len(subqueries) > 1:
                queries_pesquisa.extend(subqueries[:3])
                logger.info("Query Decomposta em: %s", subqueries[:3])
        except Exception as exc:
            logger.warning("Falha na decomposição da query: %s", exc)

    # ── Retrieval híbrido (FAISS + BM25) para múltiplas queries ──────────────
    t_ret_start = time.perf_counter()

    faiss_k = settings.faiss_k * 2 if modo_resumo else settings.faiss_k
    bm25    = await get_bm25_retriever(uc_limpa)

    tarefas_retrieval = []
    for query_q in queries_pesquisa:
        tarefas_retrieval.append(loop.run_in_executor(executor, vs.similarity_search, query_q, faiss_k))
        tarefas_retrieval.append(loop.run_in_executor(executor, bm25.invoke, query_q))

    resultados_pesquisas = await asyncio.gather(*tarefas_retrieval)

    docs_hibridos = _rrf_fusion(resultados_pesquisas, k=settings.rrf_k)
    logger.info("RRF fundiu %d pesquisas → %d docs únicos.", len(tarefas_retrieval), len(docs_hibridos))

    t_retrieval = time.perf_counter() - t_ret_start

    # ── Re-Ranking com Context Compression ───────────────────────────────────
    t_rerank_start = time.perf_counter()

    paragrafos_comprimidos = []
    for doc in docs_hibridos[:15]:
        linhas    = doc.page_content.split("\n")
        cabecalho = linhas[0] if linhas[0].startswith("[CABEÇALHO FONTE:") else ""
        texto_corpo = "\n".join(linhas[1:]) if cabecalho else doc.page_content

        pars = [p.strip() for p in texto_corpo.split("\n\n") if len(p.strip()) > 20]
        for p in pars:
            paragrafos_comprimidos.append(f"{cabecalho}\n{p}" if cabecalho else p)

    if not paragrafos_comprimidos:
        paragrafos_comprimidos = [doc.page_content for doc in docs_hibridos[:10]]

    pares = [[texto_final_aluno, p] for p in paragrafos_comprimidos]
    notas = await loop.run_in_executor(executor, reranker.predict, pares)

    pars_ordenados = sorted(zip(paragrafos_comprimidos, notas), key=lambda x: x[1], reverse=True)
    score_max      = float(pars_ordenados[0][1]) if pars_ordenados else 0.0

    final_k = settings.final_k * 3 if modo_resumo else settings.final_k * 2
    textos_finais = [
        p_texto
        for p_texto, score in pars_ordenados[:final_k]
        if score > settings.score_minimo
    ]

    t_rerank = time.perf_counter() - t_rerank_start

    if not textos_finais:
        logger.info("Nenhum fragmento acima do score mínimo para UC '%s'.", uc_limpa)
        return {
            "status":             "sucesso",
            "pergunta_original":  texto_final_aluno,
            "query_expandida":    queries_pesquisa[0],
            "resposta_stu": (
                f"Desculpa, mas não encontrei informação relevante sobre esse tema "
                f"nos documentos validados de {uc_nome}. "
                "Sugiro que consultes o teu professor ou os slides da aula."
            ),
            "fontes_consultadas": [],
        }

    contexto_recuperado = "\n\n---\n\n".join(textos_finais)

    # ── Prompt final LLM ──────────────────────────────────────────────────────
    t_llm_start = time.perf_counter()

    prompt = _prompt_rag(
        uc=uc_nome,
        contexto=contexto_recuperado,
        pergunta_original=texto_final_aluno,
        preferencia=preferencia,
        tem_imagem=tem_imagem,
        modo_resumo=modo_resumo,
    )

    try:
        resposta_limpa = await chamar_iaedu(prompt, thread_id, request)
    except (RetryError, Exception) as exc:
        logger.error("Erro na chamada IAedu após retries: %s", exc)
        resposta_limpa = "Erro de comunicação com o assistente. Tenta novamente em breve."

    t_llm = time.perf_counter() - t_llm_start

    # ── Integração Google Calendar ────────────────────────────────────────────
    if preferencia == PreferenciaEnum.plano and "[CALENDARIO]" in resposta_limpa:
        try:
            bloco_cal = re.search(r"\[CALENDARIO\](.*?)\[/CALENDARIO\]", resposta_limpa, re.DOTALL)
            if bloco_cal:
                linhas_plano = bloco_cal.group(1).strip().split("\n")
                service      = await loop.run_in_executor(executor, obter_servico_calendario)

                if service:
                    # ✅ FIX #12: datetime timezone-aware em vez de utcnow() deprecated
                    agora = datetime.datetime.now(datetime.timezone.utc)
                    for linha in linhas_plano:
                        partes = linha.split("|")
                        if len(partes) >= 2:
                            try:
                                dia_offset = int(partes[0].strip())
                                tema       = partes[1].strip()

                                data_evento = agora + datetime.timedelta(days=dia_offset)
                                start_time  = data_evento.replace(hour=10, minute=0, second=0, microsecond=0)
                                end_time    = start_time + datetime.timedelta(hours=2)

                                evento_dict = {
                                    "summary":     f"📚 Estudo {uc_nome}: {tema}",
                                    "description": f"Plano de estudo gerado pelo TUTs para a UC de {uc_nome}. Bom estudo!",
                                    "start": {"dateTime": start_time.isoformat(), "timeZone": "Europe/Lisbon"},
                                    "end":   {"dateTime": end_time.isoformat(),   "timeZone": "Europe/Lisbon"},
                                }

                                # ✅ FIX #2: disparar com callback de erro
                                _disparar_background(
                                    loop.run_in_executor(executor, _criar_evento_google_sync, service, evento_dict),
                                    f"calendario_evento_dia_{dia_offset}",
                                )
                            except ValueError:
                                continue

                resposta_limpa = re.sub(
                    r"\[CALENDARIO\].*?\[/CALENDARIO\]", "", resposta_limpa, flags=re.DOTALL
                ).strip()
                resposta_limpa += "\n\n📅 **Boa notícia! Os blocos de estudo deste plano foram adicionados automaticamente ao teu Google Calendar! Bom estudo!**"

        except Exception as exc:
            logger.error("Erro no módulo de Calendário: %s", exc)

    fontes_encontradas = set(re.findall(r"\[(.*?:\d+)\]", resposta_limpa))

    resposta_final = {
        "status":             "sucesso",
        "pergunta_original":  texto_final_aluno,
        "query_expandida":    queries_pesquisa,
        "resposta_stu":       resposta_limpa,
        "fontes_consultadas": list(fontes_encontradas),
    }

    # ✅ FIX #7: deque com maxlen gere o limite automaticamente — sem pop(0) manual
    # ✅ FIX #1: deepcopy para não guardar referência mutável na cache
    semantic_cache[uc_limpa].append({
        "emb":      query_emb,
        "resposta": copy.deepcopy(resposta_final),
    })

    # ✅ FIX #2: registo assíncrono com callback de erro
    _disparar_background(
        loop.run_in_executor(
            executor,
            registar_interacao,
            settings.sqlite_db,
            uc_limpa,
            thread_id,
            texto_final_aluno,
            json.dumps(queries_pesquisa, ensure_ascii=False),
            contexto_recuperado[:2000],
            resposta_limpa,
            score_max,
            t_retrieval,
            t_rerank,
            t_llm,
            False,
        ),
        "registar_interacao",
    )

    return resposta_final


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8001)