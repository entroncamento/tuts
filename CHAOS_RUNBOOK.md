# 🧪 Chaos Engineering Runbook (TUT’S v3 – Executable Version)

Este runbook transforma o plano de Chaos Engineering do TUT’S num conjunto operacional e executável, com ferramentas reais, comandos, ordem de execução e guardrails de segurança.

## 🧭 0. Objetivo do Runbook

Validar o comportamento do sistema TUT’S sob falhas reais controladas, garantindo:
- Degradação graciosa (não crash)
- Recuperação automática
- Estabilidade de SSE, Redis e RAG
- Isolamento de falhas entre serviços
- Integridade de memória e workers

## 🛑 0.1 SAFETY GATES (OBRIGATÓRIO)

Antes de qualquer teste:
- [ ] **Criar snapshot do sistema:**
  ```bash
  docker compose -f compose.prod.yaml ps
  docker stats --no-stream > pre-chaos-stats.log
  ```
- [ ] **Garantir rollback disponível:** `git tag pre-chaos-safe`
- [ ] **Verificar Grafana ativo:** Dashboards carregados em `http://localhost:3000`.
- [ ] **Definir kill switch:** `docker compose -f compose.prod.yaml down`

## 🧱 1. TOOLING STACK (INSTALAÇÃO)

### 1.1 k6 (load testing)
```bash
sudo apt install k6
```

### 1.2 Toxiproxy (failure injection)
```bash
docker run -d -p 8474:8474 -p 8666:8666 shopify/toxiproxy
```
CLI: `go install github.com/Shopify/toxiproxy/v2/cmd/toxiproxy@latest`

### 1.3 Linux network chaos tools (tc/netem)
```bash
sudo apt install iproute2
```

## 🔥 2. FAILURE INJECTION SETUP

### 2.1 Redis latency injection
```bash
toxiproxy-cli create redis -l localhost:8666 -u localhost:6379
toxiproxy-cli toxic add redis -t latency -a latency=2000
```

### 2.2 PostgreSQL degradation (Neon simulation)
```bash
sudo tc qdisc add dev eth0 root netem delay 200ms 100ms
# Rollback: sudo tc qdisc del dev eth0 root netem
```

### 2.3 RAG service artificial slowdown
```bash
docker exec tuts-rag tc qdisc add dev eth0 root netem delay 3000ms
```

## 📡 3. SSE CHAOS TEST (CORE SCENARIO)

### 3.1 k6 SSE Storm Test (`sse_storm.js`)
```javascript
import http from 'k6/http';
import { sleep } from 'k6';

export const options = {
  vus: 200,
  duration: '2m',
};

export default function () {
  http.get('http://localhost:8000/api/chat/stream');
  sleep(1);
}
```
Execução: `k6 run sse_storm.js`

## 💣 4. FAILURE SCENARIOS (EXECUTION ORDER)

- **Phase 1 — Baseline:** `k6 run baseline.js` (sem falhas).
- **Phase 2 — Single Failure:** Injetar latência Groq ou Redis.
- **Phase 3 — Multi-failure Chaos:** Ativar latência simultânea em Redis e Network.
- **Phase 4 — Full Chaos (Peak Stress):** Todos os serviços degradados sob carga máxima.

## 📊 5. OBSERVABILITY DURING CHAOS

Utilize o Grafana para monitorizar os thresholds críticos:
- **CPU:** `> 90%`
- **RAM:** `> 85%`
- **SSE latency:** `> 3s`
- **Redis errors:** `> 5%`

## 🧠 6. VALIDATION CHECKS

1.  **Circuit Breaker:** Verificar estado `OPEN` após falhas.
2.  **Worker Recovery:** Logs devem mostrar restart `< 5s`.
3.  **SSE Recovery:** Reconnect automático sem perda de estado.

## 🧯 8. KILL SWITCH PROCEDURE
Se o sistema ficar instável:
```bash
docker compose down
git checkout pre-chaos-safe
docker compose up -d
```

## 🏁 10. FINAL RESULT
Se todos os testes passarem, o TUT’S é oficialmente: **Chaos-tested, Failure-resilient, Production-proven SaaS.**
