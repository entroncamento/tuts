# Guia de Produção Avançado - Projeto TUT'S

Este documento detalha a arquitetura, configurações e procedimentos avançados para o deployment do sistema TUT'S em ambiente de produção real, focando-se em hardening, observabilidade e escalabilidade.

## 🏗️ Arquitetura de Produção V2

O sistema opera agora com uma infraestrutura robusta e segregada:

1.  **TUT'S Core (Laravel 11 + FrankenPHP):** Backend principal otimizado para Octane.
2.  **TUT'S Worker (Laravel Queue):** Contentor dedicado para processamento de filas, gerido por `supervisord` para garantir persistência e retry automático.
3.  **TUT'S RAG Service (FastAPI):** Serviço de IA com tracing de pedidos e healthchecks avançados.

---

## 🚀 Deployment e Otimização

### 1. Lançar Infraestrutura Completa
```bash
docker compose -f compose.prod.yaml up -d --build
```

### 2. Script de Deploy (Zero Downtime Preparation)
```bash
docker exec -it tuts-laravel /bin/bash ./deploy.sh
```

---

## 🔐 Hardening e Segurança

### 1. Request Tracing (Correlation IDs)
Todas as requisições são marcadas com um `X-Request-ID` único. Este ID é:
- Gerado pelo middleware `RequestTracing` no Laravel.
- Propagado para o serviço RAG via headers cURL.
- Incluído automaticamente em todos os logs JSON de ambos os serviços.
- Devolvido ao cliente no header da resposta para facilitar o debugging.

### 2. Segurança HTTP (Middleware)
O sistema injeta automaticamente headers de segurança em todas as respostas (exceto SSE):
- `Strict-Transport-Security (HSTS)`
- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `Content-Security-Policy (CSP Base)`

### 3. Proteção de Métricas e Ingestão
- **Ingestão:** Restrita ao `INTERNAL_TOKEN` (comunicação serviço-a-serviço).
- **Metrics:** O endpoint `/api/metrics` está protegido pelo `MetricsMiddleware`, que exige um `X-Metrics-Token` ou valida se o pedido provém da rede interna.

---

## 📡 Streaming SSE (Resiliência Avançada)

A implementação de streaming foi reforçada para produção:
- **Resiliência:** Utiliza `ignore_user_abort(true)` para garantir que o processamento do lado do servidor não é interrompido abruptamente.
- **Deteção de Quedas:** Monitoriza `connection_aborted()` para encerrar pedidos cURL ao RAG imediatamente após a saída do utilizador, poupando recursos.
- **Heartbeat:** Envia pings periódicos (`: heartbeat`) a cada 15 segundos para manter a ligação ativa através de proxies/load balancers.

---

## 📈 Observabilidade e Healthchecks

### Endpoints de Saúde (Healthchecks)
Disponíveis no Laravel (`/api/health/*`) e Python (`/live`, `/health`):
- `/health/live`: Verifica se o processo está ativo.
- `/health/ready`: Valida se as dependências base (DB, Redis, Storage) estão prontas para tráfego.
- `/health`: Report detalhado com latências de DB, Redis e estado do serviço RAG.

---

## 📊 Monitorização e Observabilidade Visual

O sistema inclui uma stack de monitorização completa integrada:

### 1. Dashboards Grafana
Aceda a `http://o-teu-ip:3000` (User: admin / Pass: definida no .env).
- **TUTS Operational Dashboard:** Monitorização em tempo real de uptime, pedidos, memória e estado das queues.

### 2. Prometheus Metrics
O Laravel expõe métricas em `/api/metrics`, protegidas pelo `MetricsMiddleware`. O Prometheus recolhe estes dados automaticamente a cada 15s.

---

## 🤖 Automação CI/CD

A pipeline está configurada via **GitHub Actions** (`.github/workflows/deploy.yml`):
1.  **Merge para `staging`:** Build e deploy automático no ambiente de testes da Oracle Cloud.
2.  **Merge para `main`:** Build e deploy automático em produção.

**Secrets Necessários no GitHub:**
- `ORACLE_PROD_HOST`, `ORACLE_STAGING_HOST`
- `ORACLE_SSH_USER`, `ORACLE_SSH_KEY`

---

## 💾 Backups e Disaster Recovery

### 1. Volumes Locais (FAISS e PDFs)
Utilize o script `backup_cloud.sh` no host:
```bash
chmod +x backup_cloud.sh
# Adicionar ao crontab (diariamente às 03:00)
0 3 * * * /home/opc/tuts-app/backup_cloud.sh
```

### 2. Base de Dados (Neon)
A base de dados Neon possui backups automáticos e Point-in-Time Recovery (PITR) ativos por defeito.

---

## 📈 Escalabilidade Horizontal

O TUT'S foi desenhado para ser **stateless**:
- **Sessions/Cache:** Redis centralizado.
- **Microservices:** Podem ser replicados atrás de um Load Balancer (necessita sticky sessions para SSE).
- **Storage:** Para escala massiva, recomenda-se migrar o volume `tuts-pdfs` para AWS S3.

---

## 🧪 Chaos Engineering e Resiliência

Para garantir que o sistema sobrevive a falhas reais, foi criado um **Chaos Engineering Runbook**. 
Este documento (`CHAOS_RUNBOOK.md`) contém procedimentos detalhados para:
- Simular latência em Redis e PostgreSQL.
- Injetar falhas na API do Groq e serviço RAG.
- Executar testes de stress SSE (*Storm Testing*) via k6.

**Procedimento de Validação:**
Antes de qualquer lançamento crítico, execute a **Phase 4 (Full Chaos)** do runbook para validar a integridade dos Circuit Breakers e a auto-recuperação dos Workers.

---

## 📝 Troubleshooting de Produção

### 1. Analisar Logs Correlacionados
Utilize o `request_id` para filtrar logs em ambos os serviços:
```bash
# No Laravel
tail -f storage/logs/laravel.log | grep "request_id\":\"UUID-AQUI\""
```

### 2. Monitorizar Workers
Os logs do supervisor estão em:
- `tuts-core/storage/logs/worker.log`

### 3. Backup de Emergência
Recomenda-se a instalação do package `spatie/laravel-backup` e a configuração de cron jobs para snapshots dos volumes FAISS.
