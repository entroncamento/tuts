# README Tecnico - TUTS

Este documento descreve o arranque local e os pontos criticos do projeto TUTS. Nao inclui segredos reais.

## 1. Visao geral

O TUTS e composto por:

| Pasta | Papel |
|---|---|
| `tuts-core/` | Laravel 12, Vue 3, Inertia, Vite, PostgreSQL via Sail. Gere autenticacao, UCs, chats, mensagens, PDF serving e proxy para o RAG. |
| `tuts-rag-service/` | FastAPI/Python. Faz ingestao de PDFs, FAISS, BM25, reranking, cache Redis e chamada a Groq. |
| `tuts-scrapers/` | Scripts auxiliares/offline, quando presentes no workspace. Nao sao necessarios para o runtime principal. |
| `.vscode/tasks.json` | Tasks locais para arrancar Redis, Sail, Vite, RAG e terminal Git. |

Fluxo principal:

```text
Vue -> Laravel /api/chat/stream -> FastAPI /perguntar -> FAISS/BM25/reranker -> Groq -> SSE -> Laravel -> Vue
```

## 2. Arranque local

### Redis Stack

O Redis Stack corre como container externo chamado `redis-stack`.

```bash
docker start redis-stack
```

Se o container nao existir:

```bash
docker run -d --name redis-stack -p 6379:6379 redis/redis-stack-server:latest
```

### Laravel/Sail

```bash
cd ${workspaceFolder}/tuts-core
./vendor/bin/sail up -d
```

O `compose.yaml` expoe:

- Laravel em `${APP_PORT:-80}:80`
- Vite em `${VITE_PORT:-5173}:5173`
- PostgreSQL em `${FORWARD_DB_PORT:-5432}:5432`
- `host.docker.internal:host-gateway` para o Laravel chegar ao RAG no host/WSL

### Vite

```bash
cd ${workspaceFolder}/tuts-core
rm -f public/hot
./vendor/bin/sail npm run dev -- --host 0.0.0.0
```

A task de VS Code espera pelo container Laravel, limpa processos antigos de Vite/npm/node dentro desse container e remove `public/hot` antes de arrancar.

### RAG Python/FastAPI

```bash
cd ${workspaceFolder}/tuts-rag-service
./venv/bin/python3 -m uvicorn main:app \
  --host 0.0.0.0 \
  --port 8001 \
  --env-file .env \
  --reload \
  --reload-dir . \
  --reload-exclude venv \
  --reload-exclude faiss_db \
  --reload-exclude __pycache__ \
  --reload-exclude pdfs
```

## 3. Variaveis de ambiente

### `tuts-core/.env`

Principais variaveis para o modo atual, com Laravel em Docker/Sail e RAG no host/WSL:

```env
APP_NAME=TUTS
APP_ENV=local
APP_URL=http://localhost:8000
APP_PORT=8000
VITE_PORT=5173

DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=tuts
DB_USERNAME=sail
DB_PASSWORD=secret
FORWARD_DB_PORT=5433

SESSION_DRIVER=database
SESSION_DOMAIN=localhost
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax

PYTHON_API_URL=http://host.docker.internal:8001/perguntar
PYTHON_HOST=host.docker.internal
PYTHON_INTERNAL_TOKEN=<mesmo valor de INTERNAL_TOKEN no RAG>
```

O Laravel le `PYTHON_API_URL` em `config/services.php` e envia o header `X-Internal-Token` com o valor de `PYTHON_INTERNAL_TOKEN`.

### `tuts-rag-service/.env`

Principais variaveis:

```env
APP_ENV=local
GROQ_API_KEY=<placeholder>
INTERNAL_TOKEN=<mesmo valor de PYTHON_INTERNAL_TOKEN>
PROFESSOR_API_KEY=<placeholder>

FRONTEND_ORIGIN=http://localhost:5173,http://127.0.0.1:5173

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
SEMANTIC_CACHE_ENABLED=true

EMBEDDING_MODEL=paraphrase-multilingual-MiniLM-L12-v2
RERANKER_MODEL=cross-encoder/mmarco-mMiniLMv2-L12-H384-v1
SCORE_MINIMO=-10.0
RERANK_MIN_SCORE_CONTEXTO=-8
RRF_K=60
SSE_BUFFER_CHARS=180
UC_JSON_PATH=database/data/cadeiras_mtc.json
```

Nao copiar valores reais de `.env` para documentacao ou commits.

## 4. FAISS e documentos criticos

Os indices FAISS vivem em:

```text
tuts-rag-service/faiss_db/
```

Cada UC deve ter uma pasta normalizada pelo mesmo algoritmo de `core.utils.limpar_nome_uc()`. Exemplo:

```text
Teorias da Comunicacao -> teorias_da_comunicacao
```

Leituras usam `resolver_pasta_faiss_uc()`:

1. tenta primeiro a pasta canonica;
2. se nao existir, percorre `faiss_db`;
3. compara nomes normalizados;
4. usa uma pasta legacy equivalente se existir;
5. nunca renomeia nem apaga pastas.

Novas ingestoes usam `pasta_faiss_canonica_uc()` e criam sempre o nome canonico.

Ficheiros criticos por UC:

| Ficheiro | Papel | Regra |
|---|---|---|
| `index.faiss` | Indice vetorial | Nao apagar sem validacao |
| `index.pkl` | Docstore/metadados LangChain | Nao apagar sem validacao |
| `manifest.json` | Versao e metadados da ingestao | Se faltar, avisar e usar `sem_versao` |

Documentos originais:

```text
tuts-core/storage/app/public/pdfs/
```

Estes PDFs tambem nao devem ser apagados sem validacao.

Sanity check:

```bash
cd ${workspaceFolder}/tuts-rag-service
./venv/bin/python3 scripts/sanity_faiss_paths.py
```

ou:

```bash
cd ${workspaceFolder}/tuts-rag-service
python scripts/sanity_faiss_paths.py
```

## 5. Ingestao e cache

Endpoint professor:

```text
POST /ingestao
Header: X-API-Key: <PROFESSOR_API_KEY>
```

O fluxo valida PDFs, copia para `tuts-core/storage/app/public/pdfs`, gera chunks, atualiza/cria FAISS, escreve `manifest.json` e invalida cache da UC.

A cache Redis usa a UC normalizada e a versao do manifest. Se o manifest nao existir ou nao puder ser lido, a versao segura e:

```text
sem_versao
```

## 6. Tasks VS Code

A task principal e:

```text
TUTS: Arrancar Tudo
```

Ela arranca em paralelo:

1. Redis Stack
2. Backend Laravel/Sail
3. Frontend Vue/Vite
4. RAG Python/FastAPI
5. Terminal Git na raiz do workspace

## 7. Riscos conhecidos

- O RAG Python ainda corre fora do Docker.
- Redis Stack corre como container externo, fora do `compose.yaml`.
- `faiss_db` e PDFs sao dados criticos e devem ser tratados como persistentes.
- Pastas FAISS antigas podem ter nomes legacy; o resolver suporta leitura, mas nao faz renames automaticos.
- `manifest.json` pode faltar em indices antigos; isto deve gerar aviso, nao recriacao automatica.
- `tuts-core/resources/js/Pages/Welcome.vue` nao existe; a rota `/` redireciona para `/novo`, que renderiza a entrada real `TutsNew`.
- `resources/js/imports/Frame5005` parece legacy e nao e importado por paginas atuais, mas nao deve ser apagado sem confirmacao.
- `tuts-rag-service/package.json` parece residual, mas requer confirmacao antes de limpeza.

## 8. Checklist para outro colega

- [ ] Instalar Docker e Docker Compose.
- [ ] Instalar Composer.
- [ ] Instalar Node.js/npm.
- [ ] Instalar Python.
- [ ] Em `tuts-core`, correr `composer install`.
- [ ] Em `tuts-core`, correr `npm install`.
- [ ] Criar `tuts-core/.env` a partir de `.env.example`.
- [ ] Gerar `APP_KEY` com `./vendor/bin/sail artisan key:generate`.
- [ ] Criar `tuts-rag-service/.env` a partir de `.env.example`.
- [ ] Confirmar que `PYTHON_INTERNAL_TOKEN` e igual a `INTERNAL_TOKEN`.
- [ ] Confirmar que `PYTHON_API_URL=http://host.docker.internal:8001/perguntar`.
- [ ] Confirmar que existe `tuts-rag-service/faiss_db`.
- [ ] Confirmar que os PDFs necessarios existem em `tuts-core/storage/app/public/pdfs`.
- [ ] Arrancar Redis Stack.
- [ ] Arrancar Laravel/Sail.
- [ ] Arrancar Vite.
- [ ] Arrancar RAG FastAPI.
- [ ] Correr `scripts/sanity_faiss_paths.py`.
- [ ] Correr `./vendor/bin/sail artisan route:list`.
- [ ] Correr `npm run build`.
- [ ] Correr `./venv/bin/python3 -m compileall .` no RAG.

## 9. Ficheiros de apoio gerados

- `ENV_AUDIT_TUTS.md`: duplicados de `.env` sem expor valores.
- `CLEANUP_CANDIDATES_TUTS.md`: candidatos a limpeza, com risco e confirmacao necessaria.
