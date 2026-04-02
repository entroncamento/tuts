import os
from concurrent.futures import ThreadPoolExecutor
from langchain_huggingface import HuggingFaceEmbeddings
from sentence_transformers import CrossEncoder
import easyocr
from config import settings, logger

logger.info("A carregar embeddings...")
embeddings_model = HuggingFaceEmbeddings(model_name=settings.embedding_model)

logger.info("A carregar motor OCR...")
leitor_ocr = easyocr.Reader(["pt"])

logger.info("A carregar modelo de Re-Ranking...")
reranker = CrossEncoder(settings.reranker_model)

_cpu_count = os.cpu_count() or 1
executor = ThreadPoolExecutor(max_workers=min(32, _cpu_count + 4))