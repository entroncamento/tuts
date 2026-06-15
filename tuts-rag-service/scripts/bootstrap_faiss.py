import os
import sys
import shutil
import urllib.request
import tarfile
import zipfile
from pathlib import Path

# Configuração via variáveis de ambiente (podem ser definidas no docker-compose.prod.yaml)
BOOTSTRAP_URL = os.getenv("BOOTSTRAP_FAISS_URL")
FAISS_DB_DIR = Path(os.getenv("BASE_FAISS_DIR", "/app/faiss_db"))

def _sincronizar_faiss_do_hub() -> None:
    """
    Descarrega o faiss_db/ do Hugging Face Dataset repo para o disco local.
    Se HF_TOKEN ou HF_DATASET_REPO não estiverem definidos, salta silenciosamente.
    Nunca bloqueia o arranque nem lança excepções não tratadas.
    """
    hf_token = os.getenv("HF_TOKEN")
    hf_repo = os.getenv("HF_DATASET_REPO")

    if not hf_token or not hf_repo:
        print("[BOOTSTRAP] HF_TOKEN ou HF_DATASET_REPO não configurados — a usar faiss_db local.")
        return

    faiss_base_dir = os.getenv("FAISS_BASE_DIR", "faiss_db")

    try:
        from huggingface_hub import snapshot_download

        print(f"[BOOTSTRAP] A sincronizar faiss_db/ de {hf_repo}...")
        snapshot_download(
            repo_id=hf_repo,
            repo_type="dataset",
            token=hf_token,
            local_dir=faiss_base_dir,
            local_dir_use_symlinks=False,
            ignore_patterns=[".gitattributes", "README.md", ".git*"],
        )
        print(f"[BOOTSTRAP] faiss_db/ sincronizado com sucesso de {hf_repo}.")
    except Exception as exc:
        print(f"[BOOTSTRAP] ⚠️  Falha ao sincronizar do HF Hub ({type(exc).__name__}: {exc}). A continuar com faiss_db local...")

def bootstrap():
    # Sincronização com HF Hub (best-effort)
    _sincronizar_faiss_do_hub()

    if not BOOTSTRAP_URL:
        print("[BOOTSTRAP] BOOTSTRAP_FAISS_URL não definida. A saltar download.")
        return

    if any(FAISS_DB_DIR.iterdir()):
        print(f"[BOOTSTRAP] Directório {FAISS_DB_DIR} não está vazio. A saltar bootstrap.")
        return

    print(f"[BOOTSTRAP] A iniciar download de {BOOTSTRAP_URL}...")
    temp_file = "/tmp/faiss_bootstrap.archive"
    
    try:
        urllib.request.urlretrieve(BOOTSTRAP_URL, temp_file)
        print("[BOOTSTRAP] Download concluído. A extrair...")

        if tarfile.is_tarfile(temp_file):
            with tarfile.open(temp_file) as tar:
                tar.extractall(path=FAISS_DB_DIR)
        elif zipfile.is_zipfile(temp_file):
            with zipfile.open(temp_file) as zip_ref:
                zip_ref.extractall(path=FAISS_DB_DIR)
        else:
            print("[BOOTSTRAP] Erro: Formato de arquivo desconhecido.")
            sys.exit(1)

        print(f"[BOOTSTRAP] Extração concluída para {FAISS_DB_DIR}")
    except Exception as e:
        print(f"[BOOTSTRAP] Erro durante o bootstrap: {e}")
        # Em produção, podemos decidir se falhamos ou se continuamos vazio
        # sys.exit(1) 

if __name__ == "__main__":
    bootstrap()
