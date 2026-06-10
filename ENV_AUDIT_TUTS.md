# ENV audit TUTS

Este relatorio foi gerado sem expor valores reais dos ficheiros `.env`.
Foram registados apenas nomes de variaveis, linhas aproximadas e se o valor estava vazio ou definido.

## Duplicados encontrados

| Variavel | Ficheiro | Linhas | Valor vencedor provavel | Sugestao |
|---|---|---:|---|---|
| `SESSION_DOMAIN` | `tuts-core/.env` | 11, 78, 82 | Linha 82, valor definido, redigido | Manter uma unica entrada. Confirmar se o dominio local deve ser `localhost` ou outro host usado no browser. |
| `SESSION_DRIVER` | `tuts-core/.env` | 10, 37, 83 | Linha 83, valor definido, redigido | Manter uma unica entrada. Confirmar se o driver final deve ser `database` para Sail/PostgreSQL. |
| `APP_ENV` | `tuts-rag-service/.env` | 17, 26 | Linha 26, valor definido, redigido | Manter uma unica entrada. Confirmar se o ambiente final e `local` durante desenvolvimento. |
| `REDIS_HOST` | `tuts-rag-service/.env` | 9, 27 | Linha 27, valor definido, redigido | Manter uma unica entrada. Para o RAG local com Redis Stack exposto no host, normalmente usar `127.0.0.1`. |

## Variaveis esperadas

`tuts-core/.env.example` foi atualizado com placeholders para:

- `PYTHON_API_URL`
- `PYTHON_HOST`
- `PYTHON_INTERNAL_TOKEN`
- `APP_PORT`
- `FORWARD_DB_PORT`
- PostgreSQL via Sail
- sessao local

`tuts-rag-service/.env.example` foi criado com placeholders para:

- `GROQ_API_KEY`
- `INTERNAL_TOKEN`
- `PROFESSOR_API_KEY`
- `FRONTEND_ORIGIN`
- `REDIS_HOST`
- `REDIS_PORT`
- `SEMANTIC_CACHE_ENABLED`
- `EMBEDDING_MODEL`
- `RERANKER_MODEL`
- `SCORE_MINIMO`
- `RERANK_MIN_SCORE_CONTEXTO`
- `RRF_K`
- `SSE_BUFFER_CHARS`
- `UC_JSON_PATH`

## Nota de seguranca

Nao foram copiados valores reais de `.env`. Antes de limpar duplicados, confirmar manualmente a intencao do valor vencedor em cada ficheiro.
