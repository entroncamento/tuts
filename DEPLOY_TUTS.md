# Deploy TUTS Backend/RAG

Este guia cobre apenas Laravel backend, RAG FastAPI, PostgreSQL, Redis e dados
persistentes. Nao colocar segredos reais neste ficheiro.

## Laravel production

Valores obrigatorios:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<dominio-laravel>
DB_CONNECTION=pgsql
DB_URL=<postgresql-url-ou-vazio>
DB_SSLMODE=require
PYTHON_API_URL=https://<rag-privado-ou-interno>/perguntar
PYTHON_ALLOWED_HOSTS=<host-do-rag>
PYTHON_INTERNAL_TOKEN=<mesmo valor de INTERNAL_TOKEN no RAG>
CHAT_STREAM_RATE_LIMIT_PER_MINUTE=15
```

Para Neon Free, usar `DB_URL` ou preencher `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME`, `DB_PASSWORD` e `DB_SSLMODE=require`.

Depois de instalar dependencias e configurar `.env`:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## RAG production

Build:

```bash
cd tuts-rag-service
docker build -t tuts-rag-service .
```

Run exemplo:

```bash
docker run --rm -p 8001:8001 \
  --env-file .env \
  -v tuts-faiss:/app/faiss_db \
  -v tuts-pdfs:/app/pdfs \
  tuts-rag-service
```

Valores obrigatorios:

```env
APP_ENV=production
SERVER_HOST=0.0.0.0
SERVER_PORT=8001
GROQ_API_KEY=<secret>
INTERNAL_TOKEN=<mesmo valor de PYTHON_INTERNAL_TOKEN no Laravel>
PROFESSOR_API_KEY=<secret forte se ingestao estiver ativa>
FRONTEND_ORIGIN=https://<dominio-frontend-ou-laravel>
LARAVEL_URL=https://<dominio-laravel-ou-url-interna>
BASE_FAISS_DIR=/app/faiss_db
PDF_STORAGE_DIR=/app/pdfs
SEMANTIC_CACHE_ENABLED=false
INGESTION_ENABLED=false
EXPOSE_PUBLIC_PDFS=false
```

`faiss_db` e PDFs sao dados persistentes. Nao devem viver apenas no filesystem
efemero do container. Usar volumes ou storage persistente do provider.

## Health checks

RAG:

```bash
curl http://<rag>/health
curl -H "X-Internal-Token: <token>" http://<rag>/ready
```

`/health` e leve e publico. `/ready` e completo e protegido: valida FAISS,
`index.faiss`, `index.pkl`, `manifest.json` e Redis quando a cache semantica
estiver ligada.

## FAISS manifest

Antes de promover uma imagem ou volume:

```bash
cd tuts-rag-service
python scripts/sanity_faiss_paths.py
python scripts/sanity_faiss_paths.py --fix-manifests
```

`--fix-manifests` so cria `manifest.json` legacy com `version=sem_versao`
quando `index.faiss` e `index.pkl` existem. Nao apaga nem recria indices.

## SSE

O fluxo esperado e:

```text
Laravel /api/chat/stream -> RAG /perguntar -> Groq stream -> Laravel SSE -> cliente
```

Se o stream falhar, verificar logs:

- Laravel: `[RAG]` e `[RAG_STREAM]`
- RAG: `[SECURITY]`, `[READY]`, `[RAG]`, `[IA_STREAM]`, `[INGESTAO]`
