<template>
    <div class="dashboard" :class="{ loaded: isLoaded }">
        <div class="bg-grid"></div>

        <aside class="dash-sidebar">
            <div class="dash-logo">
                <div class="dash-logo-icon">
                    <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5">
                        <path
                            d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>
                <div>
                    <div class="dash-logo-name">Tut's</div>
                    <div class="dash-logo-role">God Mode</div>
                </div>
            </div>

            <nav class="dash-nav">
                <button
                    v-for="item in navItems"
                    :key="item.id"
                    @click="vistaAtual = item.id"
                    :class="[
                        'dash-nav-btn',
                        vistaAtual === item.id ? 'dash-nav-btn--active' : '',
                    ]"
                >
                    <span class="dash-nav-icon">{{ item.icon }}</span>
                    <span>{{ item.label }}</span>
                </button>
            </nav>

            <div class="dash-sidebar-footer">
                <div class="dash-live-pill">
                    <span class="dash-live-dot"></span>
                    Live · {{ horaAtual }}
                </div>
            </div>
        </aside>

        <main class="dash-main">
            <header class="dash-header">
                <div>
                    <div class="dash-header-eyebrow">Painel Analítico</div>
                    <h1 class="dash-header-title">Visão da Turma</h1>
                </div>
                <div class="dash-header-right">
                    <div class="dash-uc-selector">
                        <select v-model="ucSelecionada" class="dash-select">
                            <option value="todas">Todas as UCs</option>
                            <option
                                v-for="uc in ucsDisponiveis"
                                :key="uc"
                                :value="uc"
                            >
                                {{ uc }}
                            </option>
                        </select>
                    </div>
                    <button
                        @click="carregarDados"
                        class="dash-refresh-btn"
                        :class="{ spinning: loading }"
                    >
                        <svg
                            class="w-4 h-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                    </button>
                </div>
            </header>

            <div v-if="loading" class="dash-loading">
                <div class="dash-loading-ring"></div>
                <span>A carregar métricas...</span>
            </div>

            <div v-else class="dash-content">
                <div class="kpi-grid">
                    <div class="kpi-card kpi-card--primary stagger-1">
                        <div class="kpi-label">Dúvidas Analisadas</div>
                        <div class="kpi-value">
                            <span class="kpi-number">{{
                                metricas.total_analisadas
                            }}</span>
                        </div>
                        <div class="kpi-trend kpi-trend--neutral">
                            Volume total guardado
                        </div>
                        <div class="kpi-bar">
                            <div class="kpi-bar-fill" style="width: 100%"></div>
                        </div>
                    </div>

                    <div
                        class="kpi-card stagger-2"
                        :class="
                            metricas.media_frustracao > 6
                                ? 'kpi-card--danger'
                                : 'kpi-card--safe'
                        "
                    >
                        <div class="kpi-label">Índice de Frustração</div>
                        <div class="kpi-value">
                            <span class="kpi-number">{{
                                metricas.media_frustracao
                            }}</span>
                            <span class="kpi-unit">/10</span>
                        </div>
                        <div
                            class="kpi-trend"
                            :class="
                                metricas.media_frustracao > 6
                                    ? 'kpi-trend--down'
                                    : 'kpi-trend--neutral'
                            "
                        >
                            {{
                                metricas.media_frustracao > 6
                                    ? "⚠️ Turma em risco"
                                    : "✓ Nível controlado"
                            }}
                        </div>
                        <div class="frustration-meter">
                            <div
                                class="frustration-fill"
                                :style="{
                                    width: metricas.media_frustracao * 10 + '%',
                                }"
                            ></div>
                        </div>
                    </div>

                    <div class="kpi-card stagger-3">
                        <div class="kpi-label">Saúde da Turma</div>
                        <div class="kpi-value">
                            <span class="kpi-number">{{ saudeTurma }}</span>
                            <span class="kpi-unit">%</span>
                        </div>
                        <div
                            class="kpi-trend"
                            :class="
                                saudeTurma < 50
                                    ? 'kpi-trend--down'
                                    : 'kpi-trend--up'
                            "
                        >
                            {{
                                saudeTurma < 50
                                    ? "⬇ Abaixo do ideal"
                                    : "↑ Compreensão alta"
                            }}
                        </div>
                        <div class="kpi-donut">
                            <svg viewBox="0 0 36 36" class="w-full h-full">
                                <path
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    fill="none"
                                    stroke="var(--d-border)"
                                    stroke-width="3"
                                />
                                <path
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    fill="none"
                                    stroke="var(--d-accent)"
                                    stroke-width="3"
                                    :stroke-dasharray="`${saudeTurma}, 100`"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card stagger-4">
                        <div class="kpi-label">🔥 Tópico Quente</div>
                        <div class="kpi-value">
                            <span class="kpi-number hot-topic-text">{{
                                topicoQuente
                            }}</span>
                        </div>
                        <div class="kpi-trend kpi-trend--down">
                            Responsável por {{ topicoQuenteCount }} dúvidas
                        </div>
                    </div>
                </div>

                <div class="dash-bottom">
                    <div class="chart-card stagger-5">
                        <div class="chart-header">
                            <div>
                                <div class="chart-title">
                                    Mapeamento de Dúvidas
                                </div>
                                <div class="chart-subtitle">
                                    Análise em tempo real
                                </div>
                            </div>
                            <div class="chart-legend">
                                <span
                                    class="legend-dot legend-dot--high"
                                ></span>
                                Crítico
                                <span class="legend-dot legend-dot--mid"></span>
                                Alerta
                                <span class="legend-dot legend-dot--low"></span>
                                Normal
                            </div>
                        </div>
                        <div
                            class="topicos-list"
                            v-if="Object.keys(topicosOrdenados).length > 0"
                        >
                            <div
                                v-for="(count, topico) in topicosOrdenados"
                                :key="topico"
                                class="topico-row"
                            >
                                <div class="topico-name">{{ topico }}</div>
                                <div class="topico-bar-wrap">
                                    <div
                                        class="topico-bar-fill"
                                        :class="getTopicoClass(count)"
                                        :style="{
                                            width: getTopicoWidth(count),
                                        }"
                                    ></div>
                                </div>
                                <div class="topico-count">{{ count }}</div>
                            </div>
                        </div>
                        <div v-else class="empty-state">
                            A IA ainda não recolheu dados suficientes.
                        </div>
                    </div>

                    <div class="alerts-card stagger-6">
                        <div class="alerts-header">
                            <div class="chart-title">
                                Alertas Inteligentes (IA)
                            </div>
                            <span class="alerts-badge"
                                >{{ alertasReais.length }} avisos</span
                            >
                        </div>
                        <div class="alerts-list" v-if="alertasReais.length > 0">
                            <div
                                v-for="(alerta, i) in alertasReais"
                                :key="i"
                                class="alerta-row"
                                :class="`alerta-row--${alerta.tipo}`"
                            >
                                <div class="alerta-icon">{{ alerta.icon }}</div>
                                <div class="alerta-body">
                                    <div class="alerta-msg">
                                        {{ alerta.msg }}
                                    </div>
                                    <div class="alerta-time">
                                        {{ alerta.time }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="empty-state" style="padding: 20px">
                            Tudo calmo na turma.
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";

const loading = ref(true);
const isLoaded = ref(false);
const vistaAtual = ref("overview");
const ucSelecionada = ref("todas");
const horaAtual = ref("");

const navItems = [{ id: "overview", icon: "📊", label: "Dashboard Geral" }];

const ucsDisponiveis = ref([]);

const metricas = ref({
    total_analisadas: 0,
    media_frustracao: 0,
    topicos: {},
});

// 🧠 DERIVAÇÕES DE DADOS PARA UI "FIXE"

// 1. Saúde da Turma (Inverso da frustração)
const saudeTurma = computed(() => {
    if (!metricas.value.total_analisadas) return 100;
    const invertido = 10 - metricas.value.media_frustracao;
    return Math.max(0, Math.min(100, Math.round(invertido * 10)));
});

// 2. Ordenar Tópicos
const topicosOrdenados = computed(() => {
    const t = metricas.value.topicos;
    if (!t) return {};
    return Object.fromEntries(Object.entries(t).sort(([, a], [, b]) => b - a));
});

// 3. Tópico Quente
const topicoQuente = computed(() => {
    const keys = Object.keys(topicosOrdenados.value);
    if (keys.length === 0) return "Nenhum";
    return keys[0].length > 18 ? keys[0].substring(0, 18) + "..." : keys[0];
});

const topicoQuenteCount = computed(() => {
    const keys = Object.keys(topicosOrdenados.value);
    return keys.length > 0 ? topicosOrdenados.value[keys[0]] : 0;
});

// 4. Motor Automático de Alertas
const alertasReais = computed(() => {
    const list = [];
    const f = metricas.value.media_frustracao;
    const tKeys = Object.keys(topicosOrdenados.value);

    // Alerta de Frustração
    if (f >= 7) {
        list.push({
            tipo: "danger",
            icon: "🔴",
            msg: "Estado Crítico: A turma está com um nível de frustração elevado.",
            time: "Análise contínua",
        });
    } else if (f >= 5) {
        list.push({
            tipo: "warn",
            icon: "🟡",
            msg: "Atenção: A turma apresenta sinais de confusão moderada.",
            time: "Análise contínua",
        });
    } else if (metricas.value.total_analisadas > 0) {
        list.push({
            tipo: "ok",
            icon: "🟢",
            msg: "Excelente: A turma está a assimilar a matéria sem stress.",
            time: "Agora",
        });
    }

    // Alerta de Tópico Quente
    if (tKeys.length > 0) {
        list.push({
            tipo: "warn",
            icon: "🔥",
            msg: `O tópico "${tKeys[0]}" requer intervenção nas próximas aulas.`,
            time: "Tendência",
        });
    }

    // Alerta de Volume
    if (metricas.value.total_analisadas > 5) {
        list.push({
            tipo: "info",
            icon: "🤖",
            msg: `O Tutor Virtual já libertou o professor de responder a ${metricas.value.total_analisadas} dúvidas.`,
            time: "Sistema",
        });
    }

    return list;
});

// Funções dos Gráficos
const maxTopico = computed(() => {
    if (!metricas.value.topicos) return 1;
    const vals = Object.values(metricas.value.topicos);
    return vals.length ? Math.max(...vals) : 1;
});

const getTopicoWidth = (count) =>
    Math.max(4, (count / maxTopico.value) * 100) + "%";

const getTopicoClass = (count) => {
    const pct = count / maxTopico.value;
    if (pct >= 0.7) return "topico-bar-fill--high";
    if (pct >= 0.4) return "topico-bar-fill--mid";
    return "topico-bar-fill--low";
};

const actualizarHora = () => {
    horaAtual.value = new Date().toLocaleTimeString("pt-PT", {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
    });
};

let clockInterval;

const carregarDados = async () => {
    loading.value = true;
    try {
        const response = await fetch("/api/dashboard/metrics");
        const data = await response.json();
        metricas.value = data;
        ucsDisponiveis.value = data.ucs || [];
    } catch (error) {
        console.error("Erro ao carregar", error);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    actualizarHora();
    clockInterval = setInterval(actualizarHora, 1000);
    await carregarDados();
    setTimeout(() => (isLoaded.value = true), 100);
});

onUnmounted(() => clearInterval(clockInterval));
</script>

<style scoped>
/* ── Variáveis ──────────────────────────────────────────────────────────── */
.dashboard {
    --d-bg: #0a0a0b;
    --d-surface: #111113;
    --d-surface2: #18181b;
    --d-border: #27272a;
    --d-border2: #3f3f46;
    --d-text: #fafafa;
    --d-text2: #a1a1aa;
    --d-text3: #52525b;
    --d-accent: #5b4fe8;
    --d-accent-l: rgba(91, 79, 232, 0.12);
    --d-green: #22c55e;
    --d-red: #ef4444;
    --d-yellow: #f59e0b;
    --d-radius: 12px;
    --d-sidebar: 220px;

    display: flex;
    height: 100vh;
    background: var(--d-bg);
    color: var(--d-text);
    font-family: "Instrument Sans", "DM Sans", sans-serif;
    font-size: 14px;
    overflow: hidden;
    position: relative;
}

.bg-grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(var(--d-border) 1px, transparent 1px),
        linear-gradient(90deg, var(--d-border) 1px, transparent 1px);
    background-size: 40px 40px;
    opacity: 0.3;
    pointer-events: none;
}

.dash-sidebar {
    width: var(--d-sidebar);
    background: var(--d-surface);
    border-right: 1px solid var(--d-border);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    z-index: 1;
}

.dash-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px 16px 18px;
    border-bottom: 1px solid var(--d-border);
}

.dash-logo-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: var(--d-accent);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 0 16px rgba(91, 79, 232, 0.4);
}

.dash-logo-name {
    font-weight: 700;
    font-size: 15px;
    color: var(--d-text);
    line-height: 1;
}

.dash-logo-role {
    font-size: 10px;
    color: var(--d-accent);
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin-top: 2px;
}

.dash-nav {
    flex: 1;
    padding: 12px 10px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.dash-nav-btn {
    display: flex;
    align-items: center;
    gap: 9px;
    width: 100%;
    padding: 9px 12px;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: var(--d-text2);
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.15s ease;
    text-align: left;
    font-family: inherit;
}

.dash-nav-btn:hover {
    background: var(--d-surface2);
    color: var(--d-text);
}

.dash-nav-btn--active {
    background: var(--d-accent-l);
    color: var(--d-accent);
    border: 1px solid rgba(91, 79, 232, 0.2);
}

.dash-sidebar-footer {
    padding: 14px 16px;
    border-top: 1px solid var(--d-border);
}

.dash-live-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.2);
    color: var(--d-green);
    font-size: 11px;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 20px;
    font-variant-numeric: tabular-nums;
}

.dash-live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--d-green);
    animation: livePulse 1.5s ease-in-out infinite;
}

@keyframes livePulse {
    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.5;
        transform: scale(0.8);
    }
}

.dash-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    z-index: 1;
}

.dash-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 28px;
    border-bottom: 1px solid var(--d-border);
    background: var(--d-surface);
    flex-shrink: 0;
}

.dash-header-eyebrow {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--d-accent);
    margin-bottom: 3px;
}

.dash-header-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--d-text);
}

.dash-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.dash-select {
    background: var(--d-surface2);
    border: 1px solid var(--d-border2);
    color: var(--d-text);
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    outline: none;
}

.dash-refresh-btn {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid var(--d-border2);
    background: var(--d-surface2);
    color: var(--d-text2);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}

.dash-refresh-btn:hover {
    color: var(--d-text);
    border-color: var(--d-accent);
}
.dash-refresh-btn.spinning svg {
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.dash-loading {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    color: var(--d-text2);
}

.dash-loading-ring {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid var(--d-border2);
    border-top-color: var(--d-accent);
    animation: spin 0.8s linear infinite;
}

.dash-content {
    flex: 1;
    overflow-y: auto;
    padding: 24px 28px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.dash-content::-webkit-scrollbar {
    width: 4px;
}
.dash-content::-webkit-scrollbar-thumb {
    background: var(--d-border2);
    border-radius: 20px;
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.kpi-card {
    background: var(--d-surface);
    border: 1px solid var(--d-border);
    border-radius: var(--d-radius);
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    position: relative;
    overflow: hidden;
    opacity: 0;
    transform: translateY(12px);
}

.loaded .kpi-card {
    animation: cardIn 0.4s ease forwards;
}
.loaded .stagger-1 {
    animation-delay: 0.05s;
}
.loaded .stagger-2 {
    animation-delay: 0.1s;
}
.loaded .stagger-3 {
    animation-delay: 0.15s;
}
.loaded .stagger-4 {
    animation-delay: 0.2s;
}
.loaded .stagger-5 {
    animation-delay: 0.25s;
}
.loaded .stagger-6 {
    animation-delay: 0.3s;
}

@keyframes cardIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.kpi-card--primary {
    border-top: 2px solid var(--d-accent);
}
.kpi-card--danger {
    border-top: 2px solid var(--d-red);
}
.kpi-card--safe {
    border-top: 2px solid var(--d-green);
}

.kpi-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--d-text3);
}
.kpi-value {
    display: flex;
    align-items: baseline;
    gap: 4px;
}
.kpi-number {
    font-size: 32px;
    font-weight: 700;
    color: var(--d-text);
    line-height: 1;
}
.hot-topic-text {
    font-size: 20px;
    line-height: 1.2;
    word-break: break-word;
    color: var(--d-yellow);
}
.kpi-unit {
    font-size: 16px;
    font-weight: 500;
    color: var(--d-text2);
}
.kpi-trend {
    font-size: 11px;
    font-weight: 600;
    margin-top: 2px;
}
.kpi-trend--up {
    color: var(--d-green);
}
.kpi-trend--down {
    color: var(--d-red);
}
.kpi-trend--neutral {
    color: var(--d-text3);
}

.kpi-bar {
    height: 3px;
    background: var(--d-border);
    border-radius: 20px;
    margin-top: 8px;
    overflow: hidden;
}
.kpi-bar-fill {
    height: 100%;
    background: var(--d-accent);
    border-radius: 20px;
    transition: width 1s ease;
}

.frustration-meter {
    height: 3px;
    background: var(--d-border);
    border-radius: 20px;
    margin-top: 8px;
    overflow: hidden;
}
.frustration-fill {
    height: 100%;
    background: linear-gradient(
        90deg,
        var(--d-green),
        var(--d-yellow),
        var(--d-red)
    );
    border-radius: 20px;
    transition: width 1s ease;
}

.kpi-donut {
    width: 44px;
    height: 44px;
    margin-top: 4px;
}

.dash-bottom {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 16px;
    flex: 1;
}

.chart-card,
.alerts-card {
    background: var(--d-surface);
    border: 1px solid var(--d-border);
    border-radius: var(--d-radius);
    padding: 20px;
    opacity: 0;
    transform: translateY(12px);
}
.loaded .chart-card,
.loaded .alerts-card {
    animation: cardIn 0.4s ease forwards;
}

.empty-state {
    padding: 40px;
    text-align: center;
    color: var(--d-text3);
    font-style: italic;
}
.chart-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 24px;
}
.chart-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--d-text);
}
.chart-subtitle {
    font-size: 12px;
    color: var(--d-text3);
    margin-top: 4px;
}
.chart-legend {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    color: var(--d-text3);
}
.legend-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 2px;
}
.legend-dot--high {
    background: var(--d-red);
}
.legend-dot--mid {
    background: var(--d-yellow);
}
.legend-dot--low {
    background: var(--d-accent);
}

.topicos-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.topico-row {
    display: flex;
    align-items: center;
    gap: 16px;
}
.topico-name {
    width: 200px;
    font-size: 13px;
    font-weight: 500;
    color: var(--d-text2);
    flex-shrink: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.topico-bar-wrap {
    flex: 1;
    height: 10px;
    background: var(--d-border);
    border-radius: 20px;
    overflow: hidden;
}
.topico-bar-fill {
    height: 100%;
    border-radius: 20px;
    transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}
.topico-bar-fill--high {
    background: var(--d-red);
}
.topico-bar-fill--mid {
    background: var(--d-yellow);
}
.topico-bar-fill--low {
    background: var(--d-accent);
}
.topico-count {
    width: 32px;
    text-align: right;
    font-size: 14px;
    font-weight: 600;
    color: var(--d-text2);
    font-variant-numeric: tabular-nums;
}

.alerts-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.alerts-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
    background: rgba(91, 79, 232, 0.15);
    color: var(--d-accent);
    border: 1px solid rgba(91, 79, 232, 0.2);
}
.alerts-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.alerta-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid transparent;
}
.alerta-row--danger {
    background: rgba(239, 68, 68, 0.07);
    border-color: rgba(239, 68, 68, 0.15);
}
.alerta-row--warn {
    background: rgba(245, 158, 11, 0.07);
    border-color: rgba(245, 158, 11, 0.15);
}
.alerta-row--info {
    background: rgba(91, 79, 232, 0.07);
    border-color: rgba(91, 79, 232, 0.15);
}
.alerta-row--ok {
    background: rgba(34, 197, 94, 0.07);
    border-color: rgba(34, 197, 94, 0.15);
}
.alerta-icon {
    font-size: 13px;
    flex-shrink: 0;
    margin-top: 1px;
}
.alerta-body {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.alerta-msg {
    font-size: 12px;
    font-weight: 500;
    color: var(--d-text);
    line-height: 1.4;
}
.alerta-time {
    font-size: 10px;
    color: var(--d-text3);
}
</style>
