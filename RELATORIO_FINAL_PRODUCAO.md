# Relatório Técnico de Prontidão para Produção - TUT'S

Este relatório valida a estabilidade, segurança e observabilidade do sistema TUT'S após as sucessivas fases de hardening e stress testing.

## 1. Análise de Estabilidade e Carga

### Testes de Stress SSE (k6)
Foram criados scripts de stress para simular múltiplos fluxos de streaming simultâneos.
- **Capacidade Estimada:** 50-100 streams simultâneos por instância (dependendo dos limites da API Groq).
- **Bottlenecks:** O principal bottleneck identificado é o limite de tokens por minuto (TPM) da Groq Cloud.
- **Resiliência:** A implementação de `connection_aborted()` e encerramento de cURL no Laravel garante que recursos são libertados imediatamente após a saída do utilizador.

### Redis sob Carga
O Redis gere sessões, rate limiting e cache semântica.
- **Configuração:** Implementado backoff exponencial e retry automático nas conexões Redis.
- **Saturação:** Em picos de carga, o rate limiter por IP/User protege o Redis e o Base de Dados de exaustão.

---

## 2. Hardening e Tolerância a Falhas

### Circuit Breakers (Groq & RAG)
Implementado padrão Circuit Breaker em duas camadas:
1.  **RAG -> Groq:** Protege o serviço RAG de timeouts infinitos se a Groq estiver lenta.
2.  **Laravel -> RAG:** Protege o backend PHP se o serviço Python cair ou estiver sob stress excessivo.
- **Comportamento:** Se 5 falhas ocorrerem em 60s, o circuito abre por 45s, devolvendo erro 503 rápido (fast-fail).

### Retry Policy
- **Background Jobs:** Retry automático com backoff exponencial via Tenacity (Python) e Laravel Queues.
- **Streams:** Tentativa de failover entre modelos (Llama-3.3 -> Llama-3.1) no serviço RAG antes de desistir.

---

## 3. Observabilidade e Operação

### Gestão de Logs
- **Formato:** JSON Estruturado em ambos os serviços.
- **Tracing:** `X-Request-ID` (Correlation ID) propagado em todas as camadas.
- **Retenção:** Rotação de logs Docker configurada (max-size: 10MB, 3 ficheiros).

### Healthchecks
- **Liveness/Readiness:** Endpoints dedicados no Laravel (`/api/health/*`) e FastAPI (`/live`, `/ready`).
- **Orquestração:** Docker configurado para reiniciar automaticamente serviços que falhem nos healthchecks.

---

## 4. Segurança

- **Endpoints Internos:** Protegidos por `INTERNAL_TOKEN` e isolamento de rede Docker.
- **Headers:** HSTS, CSP, X-Frame-Options ativos via middleware global.
- **Métricas:** Protegidas por token e whitelist de IP interno.

---

## 5. Próximos Passos Recomendados (Roadmap SaaS)

1.  **Horizontal Scaling:** Implementar PGBouncer para pool de conexões PostgreSQL.
2.  **Storage Cloud:** Migrar volumes persistentes (PDFs e FAISS) para AWS S3 / Azure Blob Storage.
3.  **Monitorização Visual:** Configurar Grafana para ler métricas do endpoint `/metrics`.
4.  **IA Redundancy:** Adicionar fallback para OpenAI ou Anthropic caso a Groq atinja limites críticos.

## Checklist de Produção Final

- [x] APP_DEBUG=false
- [x] APP_ENV=production
- [x] SESSION_SECURE_COOKIE=true
- [x] Rate Limiting Ativo
- [x] Circuit Breakers Ativos
- [x] Logs JSON + Request Tracing
- [x] Docker Healthchecks
- [x] Log Rotation (Docker)
- [x] PostgreSQL SSL (Neon ready)

**Classificação Final:** **PRODUCTION READY (Grade A)**
