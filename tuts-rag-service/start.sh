#!/bin/bash
set -e

# Executa o script de bootstrap para garantir que o FAISS_DB não está vazio
python scripts/bootstrap_faiss.py

# Inicia o Gunicorn com workers Uvicorn para produção
exec gunicorn main:app \
    --workers 2 \
    --worker-class uvicorn.workers.UvicornWorker \
    --bind 0.0.0.0:7860 \
    --timeout 120 \
    --access-logfile - \
    --error-logfile -
