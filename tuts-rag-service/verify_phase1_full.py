import os
import sys
import json
import shutil
from unittest.mock import MagicMock
from pathlib import Path

# Adicionar o diretório atual ao path para importar os módulos locais
sys.path.append(os.getcwd())

# 1. Mocks de Segurança para evitar carregar modelos pesados e tocar em redes
import core.ml_models
core.ml_models.embeddings_model = MagicMock()
core.ml_models.embeddings_model.embed_documents.side_effect = lambda texts: [[0.1] * 384 for _ in texts]
core.ml_models.embeddings_model.embed_query.side_effect = lambda text: [0.1] * 384

# Mock do HF Sync para evitar chamadas de rede
import services.ingestao
services.ingestao._sincronizar_uc_para_hub = MagicMock()

from services.ingestao import build_index
from langchain_community.vectorstores import FAISS

def verify_full_lifecycle():
    print("=== INICIANDO VERIFICAÇÃO DE PROPAGAÇÃO DE METADADOS (PHASE 1) ===")
    
    test_pdf = "../tuts-core/public/pdfs/Teoria_da_Comunicacao_Resumo_TC.pdf"
    if not os.path.exists(test_pdf):
        print(f"❌ Erro: PDF de teste não encontrado em {test_pdf}")
        return

    metadata_extra = {
        "context_id": 42,
        "context_type": "uc",
        "material_id": 256,
        "section_id": 14,
        "source": "official",
        "verified": True,
        "ingestion_id": 981
    }

    uc_name = "test_metadata_uc"
    db_path = Path(f"faiss_db/{uc_name}")
    
    # Limpar ambiente de teste
    if db_path.exists():
        shutil.rmtree(db_path)

    print(f"\n1. Executando build_index...")
    num_chunks, chunks = build_index(
        temp_path=test_pdf,
        filename="test_file.pdf",
        uc=uc_name,
        chunk_size=1000,
        chunk_overlap=200,
        metadata_extra=metadata_extra
    )

    # 2. Verificar sobrevivência após chunking
    print("\n2. Verificando sobrevivência após chunking (split_documents):")
    sample_chunk = chunks[0]
    
    required_keys = ["context_id", "context_type", "material_id", "section_id", "source", "verified", "ingestion_id", "page"]
    all_ok = True
    for key in required_keys:
        val = sample_chunk.metadata.get(key)
        status = "✅" if val is not None else "❌"
        print(f"   {status} {key}: {val}")
        if val is None: all_ok = False

    if all_ok:
        print("   ✅ Metadados sobreviveram ao processamento em memória.")
    else:
        print("   ❌ Falha: Metadados perdidos durante o processamento.")

    # 3. Verificar persistência FAISS
    print("\n3. Verificando persistência e recarregamento FAISS:")
    try:
        # Recarregar do disco
        vs_reloaded = FAISS.load_local(
            str(db_path),
            core.ml_models.embeddings_model,
            allow_dangerous_deserialization=True
        )
        
        # Obter todos os docs do store recarregado
        reloaded_docs = list(vs_reloaded.docstore._dict.values())
        sample_reloaded = reloaded_docs[0]
        
        print(f"   Index recarregado com {len(reloaded_docs)} documentos.")
        print("\n--- Exemplo real de metadados após RELOAD ---")
        print(json.dumps(sample_reloaded.metadata, indent=2))
        
        all_ok_disk = True
        for key in required_keys:
            if key not in sample_reloaded.metadata:
                all_ok_disk = False
                print(f"   ❌ Campo em falta no disco: {key}")
        
        if all_ok_disk:
            print("\n✅ SUCESSO FINAL: Os metadados sobreviveram a todo o ciclo de vida (Injeção -> Chunking -> FAISS Save -> FAISS Reload).")
        else:
            print("\n❌ FALHA: Metadados incompletos após recarregamento do disco.")
            
    except Exception as e:
        print(f"   ❌ Erro ao recarregar FAISS: {e}")

    # Limpeza final
    if db_path.exists():
        shutil.rmtree(db_path)

if __name__ == "__main__":
    verify_full_lifecycle()
