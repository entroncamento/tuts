---
title: TUTS RAG Service
emoji: 🎓
colorFrom: blue
colorTo: green
sdk: docker
app_port: 8001
pinned: false
---

# TUTS RAG Service

Backend RAG para o projecto TUTS. FastAPI + LangChain + FAISS + Groq.

## Variáveis de ambiente obrigatórias

| Variável | Descrição |
|---|---|
| `GROQ_API_KEY` | Chave da API Groq |
| `INTERNAL_TOKEN` | Token de autenticação interna (igual ao `PYTHON_INTERNAL_TOKEN` do Laravel) |
| `PROFESSOR_API_KEY` | Chave de acesso ao endpoint de ingestão |
| `HF_TOKEN` | Token HF com permissão write (persistência FAISS) |
| `HF_DATASET_REPO` | Repositório HF Dataset para o faiss_db (ex: `username/tuts-faiss-db`) |
| `FRONTEND_ORIGIN` | Origens permitidas pelo CORS |
| `SEMANTIC_CACHE_ENABLED` | `false` no Spaces (sem Redis) |
