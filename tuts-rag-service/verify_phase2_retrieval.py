import os
import sys
import json
import shutil
from unittest.mock import MagicMock
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor

# Adicionar o diretório atual ao path para importar os módulos locais
sys.path.append(os.getcwd())

# 1. Mocks de ML
from langchain_community.embeddings import FakeEmbeddings
import core.ml_models
core.ml_models.embeddings_model = FakeEmbeddings(size=384)
from concurrent.futures import ThreadPoolExecutor
core.ml_models.executor = ThreadPoolExecutor(max_workers=2)
# Mock do reranker para retornar scores decrescentes
core.ml_models.reranker = MagicMock()
core.ml_models.reranker.predict.side_effect = lambda pairs: [1.0 - (i * 0.1) for i in range(len(pairs))]

from langchain_core.documents import Document
from langchain_community.vectorstores import FAISS
from core.retrieval import executar_retrieval

async def verify_retrieval_filtering():
    print("=== INICIANDO VERIFICAÇÃO DE RETRIEVAL FILTRADO (PHASE 2) ===")
    
    # Criar documentos de teste (Rich Index)
    docs = [
        Document(page_content="Conteúdo Material 1 Secção A", metadata={"material_id": 1, "section_id": 101, "context_id": 42}),
        Document(page_content="Conteúdo Material 2 Secção A", metadata={"material_id": 2, "section_id": 101, "context_id": 42}),
        Document(page_content="Conteúdo Material 3 Secção B", metadata={"material_id": 3, "section_id": 102, "context_id": 42}),
        Document(page_content="Conteúdo Material 4 Secção C", metadata={"material_id": 4, "section_id": 103, "context_id": 42}),
    ]
    
    vs_rich = FAISS.from_documents(docs, core.ml_models.embeddings_model)
    bm25_full = None # O teste vai criar o scoped
    
    print("\n--- CASO 1: Filtrar por material_id único (Material 1) ---")
    filters_1 = {"material_id": 1}
    docs_res_1, _ = await executar_retrieval(["teste"], vs_rich, bm25_full, faiss_k=2, filters=filters_1)
    print(f"   Docs retornados: {len(docs_res_1)}")
    for d in docs_res_1:
        print(f"   - {d.page_content} (ID: {d.metadata['material_id']})")
    
    print("\n--- CASO 2: Filtrar por múltiplos materialIds (1 e 3) ---")
    filters_2 = {"material_id": [1, 3]}
    docs_res_2, _ = await executar_retrieval(["teste"], vs_rich, bm25_full, faiss_k=4, filters=filters_2)
    print(f"   Docs retornados: {len(docs_res_2)}")
    for d in docs_res_2:
        print(f"   - {d.page_content} (ID: {d.metadata['material_id']})")

    print("\n--- CASO 3: Filtrar por section_id (Secção 101) ---")
    filters_3 = {"section_id": 101}
    docs_res_3, _ = await executar_retrieval(["teste"], vs_rich, bm25_full, faiss_k=4, filters=filters_3)
    print(f"   Docs retornados: {len(docs_res_3)}")
    for d in docs_res_3:
        print(f"   - {d.page_content} (ID: {d.metadata['material_id']}, Secção: {d.metadata['section_id']})")

    print("\n--- CASO 4: Filtros sem correspondência (Zero Match) ---")
    filters_4 = {"material_id": 999}
    # Deve dar fallback para UC completa se o filtro resultar em 0 docs para o BM25 scoped
    docs_res_4, _ = await executar_retrieval(["teste"], vs_rich, bm25_full, faiss_k=4, filters=filters_4)
    print(f"   Docs retornados (Fallback esperado): {len(docs_res_4)}")
    if len(docs_res_4) > 0:
        print("   ✅ Fallback para UC completa funcionou.")

    print("\n--- CASO 5: Índice Legacy (Sem material_id) ---")
    docs_legacy = [
        Document(page_content="Conteúdo Legacy 1", metadata={"page": 1}),
        Document(page_content="Conteúdo Legacy 2", metadata={"page": 2}),
    ]
    vs_legacy = FAISS.from_documents(docs_legacy, core.ml_models.embeddings_model)
    filters_5 = {"material_id": 1}
    docs_res_5, _ = await executar_retrieval(["teste"], vs_legacy, bm25_full, faiss_k=4, filters=filters_5)
    print(f"   Docs retornados: {len(docs_res_5)}")
    if "Legacy" in docs_res_5[0].page_content:
        print("   ✅ Fallback automático por deteção de index legacy funcionou.")

if __name__ == "__main__":
    import asyncio
    asyncio.run(verify_retrieval_filtering())
