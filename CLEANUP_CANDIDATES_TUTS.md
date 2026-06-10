# Cleanup candidates TUTS

Nenhum item foi apagado. Este relatorio lista candidatos e comandos possiveis apenas para execucao manual depois de confirmacao.

## Candidatos

| Item | Motivo | Risco | Comando sugerido | Confirmacao necessaria |
|---|---|---|---|---|
| `Arquitetura Tecnica do Projeto TUTS.pdf:Zone.Identifier` e `tuts-rag-faiss-fix-handoff.tar.gz:Zone.Identifier` na raiz | Metadados Windows/Zone.Identifier sem utilidade em runtime | Baixo, mas os nomes podem variar em WSL/Windows | Listar primeiro com `Get-ChildItem -Force -Filter '*Zone.Identifier*'` e remover apenas os caminhos confirmados | Sim |
| `tuts-rag-service/__pycache__`, `core/__pycache__`, `routers/__pycache__`, `services/__pycache__`, `prompts/__pycache__` | Cache Python gerada localmente | Baixo; sera recriada pelo Python | `find tuts-rag-service -path '*/venv/*' -prune -o -type d -name __pycache__ -print` para rever; apagar so depois de confirmar | Sim |
| `tuts-core/public/hot` | Marcador temporario do Vite | Baixo; atualmente nao foi encontrado | `rm -f tuts-core/public/hot` | Sim |
| `tuts-core/storage/framework/views/*.php` | Views Blade compiladas geradas pelo Laravel | Baixo em desenvolvimento; Laravel recompila | `rm -f tuts-core/storage/framework/views/*.php` | Sim |
| `tuts-rag-service/package.json` e `tuts-rag-service/package-lock.json` | Parecem residuais: o RAG e Python/FastAPI, o `package.json` nao tem scripts e replica dependencias frontend | Medio; confirmar se algum fluxo offline usa `node_modules` nesta pasta | Nenhum comando ate confirmar propriedade | Sim |
| `tuts-core/resources/js/imports/Frame5005` e `Frame5005.zip` | Bundle/imports legacy; nao ha import atual fora da propria pasta | Medio; pode ser material de design/handoff ainda util | Nenhum comando ate confirmar que ja nao e usado | Sim |
| `tuts-core/resources/js/Pages/Welcome.vue` | A rota `/` ainda renderiza `Welcome`, mas o ficheiro nao existe no projeto | Alto; nao ha ficheiro para apagar e a rota pode estar quebrada | Corrigir rota para uma pagina existente ou restaurar `Welcome.vue`, depois de decisao funcional | Sim |

## Itens protegidos

Nao apagar automaticamente:

- `tuts-rag-service/faiss_db`
- `index.faiss`
- `index.pkl`
- `manifest.json`
- PDFs em `tuts-core/storage/app/public/pdfs`
- migrations
- seeders
- `.env`
- `composer.lock`
- `package-lock.json`

## Observacoes

- `tuts-rag-service/faiss_db` contem atualmente a pasta `tecnologias_avancadas_para_client_side` com `index.faiss`, `index.pkl` e `manifest.json`.
- `tuts-core/storage/app/public/pdfs` contem PDFs adicionais que nao devem ser removidos sem validacao funcional.
- `Frame5005` so apareceu como autorreferencia dentro de `resources/js/imports/Frame5005`.
- `Welcome` so apareceu em `routes/web.php`; `resources/js/Pages/Welcome.vue` nao existe.
