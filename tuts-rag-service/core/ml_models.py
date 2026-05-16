import os
from concurrent.futures import ThreadPoolExecutor
from langchain_huggingface import HuggingFaceEmbeddings
from sentence_transformers import CrossEncoder
import easyocr

from config import settings, logger

# ── 1. CARREGAMENTO SEGURO DE MODELOS (SUPPLY CHAIN PINNING) ─────────────────
logger.info("[ML_MODELS] A iniciar carregamento de modelos locais...")

# Em produção, deves fornecer o model_kwargs com a revision (commit hash do HuggingFace)
# Isto garante que ninguém adultera os pesos do modelo no repositório remoto.
embedding_kwargs = {}
if hasattr(settings, "embedding_revision") and settings.embedding_revision:
    embedding_kwargs["revision"] = settings.embedding_revision

logger.info("A carregar Embeddings...")
embeddings_model = HuggingFaceEmbeddings(
    model_name=settings.embedding_model,
    model_kwargs=embedding_kwargs
)

logger.info("A carregar Re-Ranker...")
# Opcional: Para máxima segurança, o caminho do reranker deve apontar para uma pasta local 
# pré-descarregada no container Docker, em vez de apontar para a cloud no arranque.
reranker = CrossEncoder(settings.reranker_model)

# ── 2. CARREGAMENTO CONDICIONAL DO OCR ────────────────────────────────────────
leitor_ocr = None
# O USAR_OCR deixou de estar hardcoded. Agora obedece ao settings.
if getattr(settings, "usar_ocr", False):
    logger.info("A carregar motor OCR (EasyOCR)...")
    try:
        usar_gpu = getattr(settings, "usar_gpu", False)
        leitor_ocr = easyocr.Reader(["pt"], gpu=usar_gpu)
    except Exception as e:
        logger.error("Falha ao inicializar o OCR: %s", type(e).__name__)
else:
    logger.warning("⚠️ OCR DESATIVADO por configuração (usar_ocr=False).")

# ── 3. SEGREGAÇÃO DE RECURSOS (PREVENÇÃO DE DoS E TIMEOUTS) ───────────────────
_cpu_count = os.cpu_count() or 1

# Executor RAG (Leve e Rápido): Usado para pesquisas FAISS e Reranking dos alunos
max_workers_rag = min(32, _cpu_count * 2)
executor_rag = ThreadPoolExecutor(max_workers=max_workers_rag, thread_name_prefix="rag_worker")

# Executor Ingestão (Pesado): Usado para o processamento de PDFs e OCR
# Limitado propositadamente para não asfixiar o servidor e deixar o RAG respirar
max_workers_ingestao = max(1, _cpu_count - 1)
executor_ingestao = ThreadPoolExecutor(max_workers=max_workers_ingestao, thread_name_prefix="ingest_worker")

# Alias de compatibilidade (para não quebrar os teus imports atuais imediatamente)
# Nota: Idealmente, no `routers/professores.py` deves passar a importar o `executor_ingestao`
executor = executor_rag