# AGENTS.md - Guia para agentes Codex no TUTS

Este ficheiro orienta execucoes futuras do Codex neste workspace. Mantem as
alteracoes pequenas, verificaveis e compativeis com a arquitetura existente.

## Principio base

- Le primeiro os ficheiros relevantes antes de editar.
- Nao alteres logica de negocio sem evidencia no codigo ou pedido explicito.
- Nao apagues dados, PDFs, indices FAISS, migrations, ficheiros legacy ou assets
  sem confirmacao explicita.
- Se a tarefa for apenas documental, nao alteres codigo da aplicacao.
- Preserva alteracoes existentes do utilizador. Nao uses `git reset --hard`,
  `git checkout --` ou comandos destrutivos sem pedido claro.

## Arquitetura do TUTS

O workspace tem estes modulos principais:

| Pasta | Papel |
| --- | --- |
| `tuts-core/` | Aplicacao principal: Laravel 12, PHP, Inertia, Vue 3, Pinia, Vite, Tailwind, PostgreSQL via Sail. Gere autenticacao, UCs, study spaces, materiais, chats, notificacoes, PDF serving e proxy para o RAG. |
| `tuts-rag-service/` | Servico Python/FastAPI de RAG: ingestao de PDFs, FAISS, BM25, reranking, cache Redis, chamadas a LLM/Groq e respostas SSE. |
| `.vscode/tasks.json` | Tasks locais para arrancar Redis, Laravel/Sail, Vite, RAG e terminal Git. |
| `README_TECNICO.md` | Documentacao tecnica de arranque local, variaveis, FAISS e riscos conhecidos. |

Fluxo principal de chat/RAG:

```text
Vue -> Laravel /api/chat/stream -> FastAPI /perguntar
    -> FAISS/BM25/reranker/cache -> LLM -> SSE
    -> Laravel -> Vue
```

Frontend principal:

- `tuts-core/resources/js/app/` contem a app Vue moderna: `App.vue`,
  `layout/AppShell.vue`, `router/index.ts`, `pages/`, `components/`,
  `stores/`, `services/`, `composables/` e `types/`.
- `tuts-core/resources/js/Pages/` contem paginas Inertia/Breeze e a entrada
  `TutsNew.vue`.
- `resources/js/app/services/api.ts` e o wrapper para chamadas JSON ao Laravel,
  com CSRF, `X-Requested-With`, `credentials: "same-origin"` e `ApiError`.
- `resources/js/app/stores/` contem estado global Pinia como shell, planning e
  role student/teacher.

Backend principal:

- `tuts-core/routes/web.php` contem a maioria dos endpoints usados pelo frontend
  autenticado e o fallback Inertia para a app.
- `tuts-core/routes/api.php` contem APIs com `auth:sanctum`, `throttle:api` e
  rotas internas protegidas por `internal.api`.
- Controllers da API vivem em `tuts-core/app/Http/Controllers/Api/`.
- Models principais: `User`, `Course`, `Subject`, `Chat`, `Message`,
  `StudySpace`, `SpaceFolder`, `SpaceMaterial`, `TutsNotification`.

RAG:

- `tuts-rag-service/main.py` cria a app FastAPI, configura CORS, routers e
  endpoint seguro de PDFs.
- `tuts-rag-service/config.py` centraliza settings via `.env` com Pydantic.
- Routers: `routers/sistema.py`, `routers/professores.py`,
  `routers/alunos.py`.
- Core/services: `core/` para cache, retrieval e modelos; `services/` para
  ingestao, analise, OCR, calendario, query expansion e integracoes.

## Comandos corretos

Usa preferencialmente WSL/Linux para comandos do projeto.

### Redis Stack

```bash
docker start redis-stack
```

Se nao existir:

```bash
docker run -d --name redis-stack -p 6379:6379 redis/redis-stack-server:latest
```

### Backend Laravel/Sail

```bash
cd tuts-core
./vendor/bin/sail up -d
```

Comandos uteis:

```bash
cd tuts-core
./vendor/bin/sail artisan route:list
./vendor/bin/sail artisan test
./vendor/bin/sail artisan migrate:status
./vendor/bin/sail composer test
```

`composer.json` tambem define:

```bash
composer run dev
composer test
```

Em ambiente Sail, prefere `./vendor/bin/sail ...` para comandos PHP/npm que
dependem do container.

### Frontend Vue/Vite

```bash
cd tuts-core
rm -f public/hot
./vendor/bin/sail npm run dev -- --host 0.0.0.0
```

Build:

```bash
cd tuts-core
./vendor/bin/sail npm run build
```

O `package.json` atual so define `dev` e `build`. Nao inventes `lint`,
`type-check` ou outros scripts sem confirmar que existem.

### RAG Python/FastAPI

```bash
cd tuts-rag-service
./venv/bin/python3 -m uvicorn main:app \
  --host 0.0.0.0 \
  --port 8001 \
  --env-file .env \
  --reload \
  --reload-dir . \
  --reload-exclude .venv \
  --reload-exclude venv \
  --reload-exclude faiss_db \
  --reload-exclude __pycache__ \
  --reload-exclude pdfs
```

Checks uteis:

```bash
cd tuts-rag-service
./venv/bin/python3 -m compileall .
./venv/bin/python3 scripts/sanity_faiss_paths.py
```

Se `./venv/bin/python3` nao existir, reporta isso. Nao cries outro ambiente ou
instales dependencias sem confirmar impacto.

### VS Code tasks

A task principal e:

```text
TUTS: Arrancar Tudo
```

Ela arranca em paralelo:

1. Redis Stack
2. Backend Laravel/Sail
3. Frontend Vue/Vite
4. RAG Python/FastAPI
5. Terminal Git

## Portas usadas

| Servico | Porta | Origem |
| --- | --- | --- |
| Laravel/Sail HTTP | `${APP_PORT:-80}`; local esperado `8000` no `.env` | `tuts-core/compose.yaml` |
| Vite dev server | `5173` | `vite.config.js` com `strictPort: true` |
| Vite HMR | `5173` em `localhost` | `vite.config.js` |
| PostgreSQL Sail | container `5432`, host `${FORWARD_DB_PORT:-5432}`; local esperado `5433` no `.env` | `compose.yaml` / README tecnico |
| RAG FastAPI | `8001` | task VS Code e `config.py` |
| Redis Stack | `6379` | container externo `redis-stack` |

Laravel em Docker comunica com o RAG no host por:

```env
PYTHON_API_URL=http://host.docker.internal:8001/perguntar
PYTHON_HOST=host.docker.internal
PYTHON_INTERNAL_TOKEN=<mesmo valor de INTERNAL_TOKEN no RAG>
```

## Regras de dark mode

- Tailwind usa `darkMode: "class"`.
- O tema e gerido por `resources/js/app/composables/useTheme.ts`.
- `useTheme` escreve em `document.documentElement`:
  - `data-theme="light|dark"`
  - `data-theme-mode="system|light|dark"`
  - classes `light` e `dark`
  - `style.colorScheme`
- A preferencia fica em `localStorage` com a chave `tuts-theme-mode`.
- Usa tokens de `resources/js/styles/theme.css` e cores Tailwind semanticas:
  - `bg-app-bg`, `bg-app-surface`, `bg-app-surface-muted`
  - `text-app-text`, `text-app-muted`, `text-app-soft`
  - `border-app-border`, `border-app-border-soft`
  - `bg-primary`, `text-primary-foreground`, etc.
- Evita `text-black`, `text-white`, `bg-white`, `bg-black`, hex/rgb inline e
  estilos inline de cor, exceto quando ha razao forte e suporte explicito para
  ambos os temas.
- Nao resolvas dark mode com overrides locais repetidos se um token global ja
  existe. Prefere adicionar/usar tokens semanticos.
- Ao mexer em tema, testa paginas em light e dark, incluindo inputs, modais,
  dropdowns, sidebar, topbar, cards, estados hover/focus e mensagens de erro.

## Regras de responsividade

- Mobile-first. Confirma layouts em mobile, tablet e desktop.
- Evita larguras fixas sem `max-width`, `minmax`, `clamp` ou breakpoints.
- Evita `w-screen` em containers internos se puder causar overflow horizontal.
- Usa `min-w-0` em filhos de flex/grid que contem texto truncavel.
- Usa `overflow-x-auto` apenas em superficies onde scroll horizontal e esperado
  (tabelas, timelines, calendarios), nao como solucao global.
- Garante que sidebar, topbar, dropdowns, modais, chat input, cards de UC,
  paginas de spaces e notificacoes nao sobrepoem conteudo em viewport pequeno.
- Estados loading, empty e error tambem devem caber em mobile.
- Nao uses texto gigante dentro de paineis compactos. Mantem hierarquia de
  headings proporcional ao contentor.

## Regras de TypeScript/Vue

- Mantem `@/*` como alias para `resources/js/*`.
- Tipa respostas de API com interfaces ou tipos dedicados. Evita `any`.
- Se um campo vem da API como opcional ou nullable, trata essa possibilidade na
  UI antes de renderizar.
- Centraliza tipos partilhados em `resources/js/app/types/` quando usados por
  varias paginas/componentes.
- Usa `apiFetch<T>()` para chamadas JSON ao Laravel, salvo caso justificado
  como SSE, download, upload multipart ou stream.
- Para stores Pinia, evita duplicar estado derivavel em componentes. Guarda no
  store apenas estado partilhado ou persistente.
- Nao mistures responsabilidades: paginas coordenam fluxo; componentes recebem
  props/emitem eventos; services chamam API; stores guardam estado global.
- O projeto usa `.ts` em varias zonas, mas o manifest atual nao tem script de
  type-check. Para terminar, pelo menos corre `npm run build`.

## Regras Laravel/API

- Rotas usadas pela app autenticada vivem sobretudo em `routes/web.php` dentro
  de `middleware(['auth', 'verified'])`.
- Rotas API dedicadas vivem em `routes/api.php` com `auth:sanctum`,
  `throttle:api`, `can:*` ou `internal.api` conforme o caso.
- Endpoints internos devem usar o middleware `internal.api` e token partilhado,
  nunca ficar publicos.
- Mantem formatos JSON consistentes. Inclui `message` em erros quando a UI
  precisa mostrar feedback.
- Valida inputs no backend. Para fluxos maiores, prefere `FormRequest`.
- Aplica autorizacao por utilizador em resources como spaces, folders,
  materials, chats e notificacoes. Nunca confies apenas no id vindo do frontend.
- Para uploads/PDFs, valida tipo, tamanho e ownership. Usa nomes seguros e evita
  path traversal com `basename`, `Storage` ou APIs equivalentes.
- Nao exponhas dados sensiveis em JSON: tokens, paths absolutos, prompts
  internos, variaveis `.env`, stack traces ou dados de outros utilizadores.
- Ao criar migrations, usa foreign keys, indexes e cascade/null-on-delete apenas
  quando a regra de negocio justificar.

## Regras RAG/FastAPI

- Configuracao deve vir de `tuts-rag-service/.env` e `config.py`.
- Nao hardcodes caminhos absolutos de Windows/WSL. Usa `Path`, `BASE_DIR`,
  `WORKSPACE_ROOT`, `LARAVEL_ROOT` e settings.
- Endpoints sensiveis devem exigir `X-Internal-Token` ou `X-API-Key`, conforme
  o router/fluxo.
- Mantem CORS restrito a `FRONTEND_ORIGIN`; nao uses `*` com credenciais.
- FAISS vive em `tuts-rag-service/faiss_db/`. PDFs originais vivem em
  `tuts-core/storage/app/public/pdfs/`.
- Nao apagues nem renomeies pastas FAISS legacy automaticamente. Usa os helpers
  de resolucao/canonicalizacao existentes.
- Se faltar `manifest.json`, trata como `sem_versao` e avisa; nao reconstruas
  dados silenciosamente.
- Respostas fora do contexto documental devem recusar com seguranca ou indicar
  falta de base, nao inventar fontes.

## Regras de seguranca

- Nunca commits segredos reais: `.env`, API keys, tokens internos, service
  accounts, dumps, logs sensiveis.
- `PYTHON_INTERNAL_TOKEN` em Laravel deve ser igual a `INTERNAL_TOKEN` no RAG.
- `PROFESSOR_API_KEY` protege endpoints de ingestao/professor.
- Mantem rate limits existentes em login, chat, uploads, criacao de recursos e
  endpoints internos.
- Sanitiza HTML/Markdown no frontend. O projeto tem `dompurify`, `marked` e
  `mermaid`; nao renderizes HTML cru sem sanitizacao.
- Evita logs com caminhos absolutos, tokens, prompts privados ou conteudo
  pessoal dos utilizadores.
- Para PDFs, evita path traversal e define headers como `X-Content-Type-Options:
  nosniff` quando servidos pelo Laravel.

## O que nunca deve ser hardcoded

- Tokens, API keys, passwords, connection strings, service accounts.
- URLs/hosts/portas de servicos quando ja existem variaveis `.env`.
- Paths absolutos de Windows, WSL ou maquina local.
- Anos, semestres, nomes de UCs/cursos, limites academicos e dados de estudante
  que devem vir da base de dados ou JSON canonico.
- IDs de utilizador, chat, UC, space, folder, material ou notification.
- Respostas do RAG, fontes/citacoes, scores, manifest versions ou nomes FAISS.
- Cores de tema fora dos tokens semanticos, salvo excecao justificada.
- Textos de erro duplicados quando ja existe padrao partilhado.

## Como testar antes de terminar

Escolhe os testes proporcionais ao risco da alteracao. Para mudancas pequenas de
documentacao, nao e necessario correr build/test, mas confirma que so foram
alterados ficheiros de documentacao.

Checklist recomendado:

```bash
git -c safe.directory=//wsl.localhost/Ubuntu/home/gilal/tuts-workspace status --short
```

Backend:

```bash
cd tuts-core
./vendor/bin/sail artisan route:list
./vendor/bin/sail artisan test
```

Frontend:

```bash
cd tuts-core
./vendor/bin/sail npm run build
```

RAG:

```bash
cd tuts-rag-service
./venv/bin/python3 -m compileall .
./venv/bin/python3 scripts/sanity_faiss_paths.py
```

Quando a alteracao mexe em UI:

- Abrir a app local.
- Testar pelo menos desktop e mobile.
- Verificar light e dark mode.
- Confirmar que nao ha overflow horizontal, texto sobreposto ou estados sem
  feedback.

Se um comando falhar, reporta comando, erro essencial, causa provavel e se foi
ou nao corrigido.

## Como reportar alteracoes no fim

No resumo final, inclui de forma objetiva:

- Ficheiros alterados.
- O que foi mudado e por que motivo.
- Comandos executados e resultado.
- Testes/builds nao executados e motivo.
- Problemas encontrados que ficaram fora do escopo.
- Qualquer risco residual ou decisao que dependa do utilizador.

Para reviews, lista findings primeiro com severidade e referencias a ficheiro e
linha. Para implementacoes, mantem o resumo curto e verificavel.
