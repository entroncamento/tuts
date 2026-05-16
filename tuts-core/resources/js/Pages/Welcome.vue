<template>
    <div
        class="app-root flex h-screen font-sans transition-colors duration-300"
        :class="{ dark: isDark }"
    >
        <Login v-if="!utilizador" @autenticado="aoAutenticar" />

        <template v-else>
            <aside class="sidebar flex flex-col flex-shrink-0 z-10">
                <div class="sidebar-logo">
                    <div class="logo-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            class="w-5 h-5"
                        >
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
                        <h1 class="logo-title">Tut's</h1>
                        <p class="logo-subtitle">Tutor Virtual</p>
                    </div>
                </div>

                <div class="sidebar-section-label">As Tuas Cadeiras</div>

                <nav class="sidebar-nav custom-scrollbar">
                    <button
                        v-for="uc in listaUCs"
                        :key="uc"
                        @click="selecionarUC(uc)"
                        :class="[
                            'uc-btn',
                            ucAtual === uc ? 'uc-btn--active' : '',
                        ]"
                        :title="uc"
                    >
                        <span class="uc-icon">{{
                            ucAtual === uc ? "📂" : "📁"
                        }}</span>
                        <span class="uc-name">{{ uc }}</span>
                        <span
                            v-if="ucAtual === uc"
                            class="uc-active-dot"
                        ></span>
                    </button>
                </nav>

                <div class="sidebar-footer">
                    <div class="api-status">
                        <span class="status-dot">
                            <span class="status-ping"></span>
                            <span class="status-core"></span>
                        </span>
                        <span>API Online · Porta 8001</span>
                    </div>

                    <div class="user-row">
                        <span class="user-name">{{ utilizador.name }}</span>
                        <button class="logout-btn" @click="sair" title="Sair">
                            <svg
                                class="w-3.5 h-3.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </aside>

            <div
                class="main-area flex flex-col flex-1 h-screen overflow-hidden relative"
            >
                <header class="app-header">
                    <div class="header-left">
                        <div class="breadcrumb-chip">A estudar</div>
                        <h2 class="header-uc-name">{{ ucAtual }}</h2>
                    </div>

                    <div class="header-right">
                        <div class="mode-selector">
                            <button
                                @click="ativarModoAutomatico"
                                :class="[
                                    'mode-btn',
                                    !modoManual ? 'mode-btn--active' : '',
                                ]"
                                title="Modo Automático"
                            >
                                <span class="mode-icon">⚡</span>
                                <span class="mode-label">Auto</span>
                            </button>

                            <button
                                v-for="m in modos"
                                :key="m.value"
                                @click="selecionarModoManual(m.value)"
                                :class="[
                                    'mode-btn',
                                    preferenciaAtiva === m.value
                                        ? 'mode-btn--active'
                                        : '',
                                    modoManual === m.value
                                        ? 'mode-btn--manual'
                                        : '',
                                ]"
                                :title="m.label"
                            >
                                <span class="mode-icon">{{ m.icon }}</span>
                                <span class="mode-label">{{ m.label }}</span>
                            </button>
                        </div>

                        <div
                            class="mode-source-chip"
                            :class="
                                modoManual
                                    ? 'mode-source-chip--manual'
                                    : 'mode-source-chip--auto'
                            "
                            :title="
                                modoManual
                                    ? 'Modo fixado por clique manual'
                                    : 'Modo decidido automaticamente pelo texto'
                            "
                        >
                            {{ origemModoLabel }}
                        </div>

                        <button
                            @click="toggleDarkMode"
                            class="icon-btn"
                            title="Alternar Tema"
                        >
                            <svg
                                v-if="isDark"
                                class="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
                                />
                            </svg>
                            <svg
                                v-else
                                class="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
                                />
                            </svg>
                        </button>

                        <button
                            @click="limparChat"
                            class="icon-btn"
                            title="Limpar Conversa"
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
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                />
                            </svg>
                        </button>
                    </div>
                </header>

                <main
                    class="chat-area custom-scrollbar"
                    ref="chatContainer"
                    @scroll="onScroll"
                >
                    <div v-if="mensagens.length === 0" class="welcome-state">
                        <div class="welcome-orb"></div>
                        <h3 class="welcome-title">
                            Olá, {{ utilizador.name.split(" ")[0] }}! 👋
                        </h3>
                        <p class="welcome-subtitle">
                            Estás a estudar <strong>{{ ucAtual }}</strong
                            >. Escolhe um modo acima e começa a aprender.
                        </p>

                        <div class="suggestions">
                            <button
                                v-for="s in sugestoes"
                                :key="s"
                                @click="usarSugestao(s)"
                                class="suggestion-chip"
                            >
                                {{ s }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-for="(msg, index) in mensagens"
                        :key="index"
                        v-show="
                            msg.role === 'user' ||
                            msg.content.length > 0 ||
                            (msg.quiz && msg.quiz.length > 0)
                        "
                        :class="[
                            'msg-row',
                            msg.role === 'user'
                                ? 'msg-row--user'
                                : 'msg-row--ai',
                        ]"
                    >
                        <div
                            v-if="msg.role === 'ai'"
                            class="msg-avatar msg-avatar--ai"
                        >
                            T
                        </div>

                        <div class="msg-content-wrap">
                            <div
                                v-if="msg.semContexto"
                                class="no-context-alert"
                            >
                                <svg
                                    class="w-4 h-4 flex-shrink-0"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                    />
                                </svg>
                                <span
                                    ><strong>Atenção:</strong> Não encontrei
                                    informação sobre isto nos PDFs da UC. A
                                    resposta usa conhecimento geral.</span
                                >
                            </div>

                            <div
                                :class="[
                                    'msg-bubble',
                                    msg.role === 'user'
                                        ? 'msg-bubble--user'
                                        : 'msg-bubble--ai',
                                ]"
                            >
                                <div v-if="msg.imagem" class="msg-image-wrap">
                                    <img
                                        :src="msg.imagem"
                                        class="msg-image"
                                        alt="Anexo"
                                    />
                                </div>

                                <div
                                    class="prose-content"
                                    @click="lidarComCliqueCitacao"
                                    :class="[
                                        msg.role === 'user'
                                            ? 'prose-user'
                                            : 'prose-ai',
                                        aCarregar && index === indiceAtivo
                                            ? 'streaming-cursor'
                                            : '',
                                    ]"
                                    v-html="
                                        renderMarkdown(
                                            msg.content,
                                            aCarregar && index === indiceAtivo,
                                        )
                                    "
                                ></div>
                            </div>

                            <div
                                v-if="msg.sugestoes && msg.sugestoes.length > 0"
                                class="ai-suggestions-wrap"
                            >
                                <button
                                    v-for="(s, si) in msg.sugestoes"
                                    :key="si"
                                    @click="usarSugestao(s)"
                                    class="ai-suggestion-chip"
                                >
                                    ✨ {{ s }}
                                </button>
                            </div>

                            <div class="msg-meta">
                                <span class="msg-time">{{
                                    formatarHora(msg.hora)
                                }}</span>

                                <button
                                    v-if="msg.role === 'ai' && msg.content"
                                    @click="copiarMensagem(msg.content, index)"
                                    class="copy-btn"
                                    :title="
                                        copiado === index
                                            ? 'Copiado!'
                                            : 'Copiar'
                                    "
                                >
                                    <svg
                                        v-if="copiado !== index"
                                        class="w-3.5 h-3.5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                        />
                                    </svg>
                                    <svg
                                        v-else
                                        class="w-3.5 h-3.5"
                                        style="color: #22c55e"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                    <span>{{
                                        copiado === index
                                            ? "Copiado!"
                                            : "Copiar"
                                    }}</span>
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="msg.role === 'user'"
                            class="msg-avatar msg-avatar--user"
                        >
                            {{ utilizador.name[0].toUpperCase() }}
                        </div>

                        <div
                            v-if="msg.quiz && msg.quiz.length > 0"
                            class="quiz-container"
                        >
                            <div class="quiz-header">
                                <span class="quiz-badge"
                                    >🎮 Quiz Interativo</span
                                >
                                <span
                                    class="quiz-score"
                                    v-if="quizFinalizado(msg)"
                                    >{{ pontuacaoQuiz(msg) }}/{{
                                        msg.quiz.length
                                    }}
                                    corretas</span
                                >
                            </div>

                            <div
                                v-for="(pergunta, qi) in msg.quiz"
                                :key="qi"
                                class="quiz-card"
                            >
                                <p class="quiz-question">
                                    <span class="quiz-q-num">{{ qi + 1 }}</span>
                                    {{ pergunta.pergunta }}
                                </p>

                                <div class="quiz-options">
                                    <button
                                        v-for="(opcao, oi) in pergunta.opcoes"
                                        :key="oi"
                                        @click="responderQuiz(msg, qi, oi)"
                                        :disabled="msg.respostas[qi] !== -1"
                                        :class="[
                                            'quiz-option',
                                            getQuizButtonClass(msg, qi, oi),
                                        ]"
                                    >
                                        <span class="quiz-option-letter">{{
                                            String.fromCharCode(65 + oi)
                                        }}</span>
                                        <span>{{ opcao }}</span>
                                    </button>
                                </div>

                                <div
                                    v-if="msg.respostas[qi] !== -1"
                                    :class="[
                                        'quiz-feedback',
                                        msg.respostas[qi] === pergunta.correta
                                            ? 'quiz-feedback--correct'
                                            : 'quiz-feedback--wrong',
                                    ]"
                                >
                                    <div class="mb-2">
                                        <span
                                            v-if="
                                                msg.respostas[qi] ===
                                                pergunta.correta
                                            "
                                            >🎉 Resposta certa!</span
                                        >
                                        <span v-else
                                            >❌ Errado! A resposta certa era a
                                            <strong>{{
                                                String.fromCharCode(
                                                    65 + pergunta.correta,
                                                )
                                            }}</strong
                                            >.</span
                                        >
                                    </div>

                                    <div
                                        v-if="pergunta.explicacao"
                                        class="quiz-explanation mb-2"
                                    >
                                        💡 <strong>Explicação:</strong>
                                        {{ pergunta.explicacao }}
                                    </div>

                                    <button
                                        @click="
                                            usarSugestao(
                                                `Podes aprofundar e explicar-me melhor a pergunta: '${pergunta.pergunta}'?`,
                                            )
                                        "
                                        class="quiz-explain-btn"
                                    >
                                        🧠 Aprofundar Explicação
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="aCarregar && indiceAtivo === -1"
                        class="msg-row msg-row--ai"
                    >
                        <div class="msg-avatar msg-avatar--ai">T</div>

                        <div class="typing-bubble">
                            <template v-if="statusMsg">
                                <span class="status-spinner"></span>
                                <span class="status-msg-text">{{
                                    statusMsg
                                }}</span>
                            </template>
                            <template v-else>
                                <span class="typing-dot"></span>
                                <span class="typing-dot"></span>
                                <span class="typing-dot"></span>
                            </template>
                        </div>
                    </div>
                </main>

                <transition name="fade-up">
                    <button
                        v-if="aCarregar"
                        @click="pararGeracao"
                        class="stop-btn"
                    >
                        <svg
                            class="w-4 h-4"
                            fill="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <rect
                                x="6"
                                y="6"
                                width="12"
                                height="12"
                                rx="2"
                            ></rect>
                        </svg>
                        Parar de gerar
                    </button>
                </transition>

                <Transition name="fab">
                    <button
                        v-if="mostrarScrollBtn"
                        @click="scrollToBottom"
                        class="scroll-fab"
                        title="Ir para o fundo"
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
                                stroke-width="2.5"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                </Transition>

                <footer class="app-footer">
                    <div v-if="imagemPreview" class="image-preview-wrap">
                        <img :src="imagemPreview" class="image-preview-thumb" />
                        <button
                            @click="removerImagem"
                            class="image-preview-remove"
                        >
                            ×
                        </button>
                    </div>

                    <div class="input-area">
                        <label class="attach-btn" title="Anexar Imagem">
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
                                />
                            </svg>
                            <input
                                type="file"
                                class="hidden"
                                accept="image/*"
                                @change="lidarComImagem"
                            />
                        </label>

                        <textarea
                            v-model="mensagemAtual"
                            @keydown.enter.exact.prevent="enviarMensagem"
                            @input="autoResize"
                            ref="textareaRef"
                            rows="1"
                            :placeholder="placeholderTexto"
                            class="chat-textarea"
                        ></textarea>

                        <div class="input-right">
                            <span
                                class="char-hint"
                                v-if="mensagemAtual.length > 0"
                                >{{ mensagemAtual.length }}</span
                            >

                            <button
                                @click="enviarMensagem"
                                :disabled="
                                    aCarregar ||
                                    (!mensagemAtual.trim() && !imagemFicheiro)
                                "
                                class="send-btn"
                                :class="{
                                    'send-btn--active':
                                        mensagemAtual.trim() || imagemFicheiro,
                                }"
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
                                        d="M5 12h14M12 5l7 7-7-7"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <p class="input-hint">
                        Modo: <strong>{{ modoAtual?.label }}</strong> · Origem:
                        <strong>{{ origemModoLabel }}</strong> · Enter envia ·
                        Shift+Enter nova linha
                    </p>
                </footer>
            </div>

            <Transition name="fade">
                <div
                    v-if="pdfModalAberto"
                    class="pdf-modal-overlay"
                    @click.self="fecharPdf"
                >
                    <div class="pdf-modal-container">
                        <div class="pdf-modal-header">
                            <div class="pdf-modal-title">
                                📑 Consulta de Fonte Original
                                <span class="pdf-modal-filename">{{
                                    pdfFicheiroAtual
                                }}</span>
                            </div>

                            <button
                                @click="fecharPdf"
                                class="pdf-modal-close"
                                title="Fechar Visualizador"
                            >
                                <svg
                                    class="w-5 h-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    ></path>
                                </svg>
                            </button>
                        </div>

                        <iframe
                            :src="pdfUrlAtual"
                            class="pdf-modal-iframe"
                        ></iframe>
                    </div>
                </div>
            </Transition>
        </template>
    </div>
</template>
<script setup>
import { ref, nextTick, onMounted, computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { marked } from "marked";
import DOMPurify from "dompurify";
import mermaid from "mermaid";
import cadeirasDados from "../cadeiras_mtc.json";
import Login from "./Login.vue";

mermaid.initialize({ startOnLoad: false, theme: "base" });

const page = usePage();

const modos = [
    { value: "default", label: "Tutor", icon: "💬" },
    { value: "visual", label: "Visual", icon: "🧩" },
    { value: "plano", label: "Plano", icon: "🗓️" },
    { value: "quiz", label: "Quiz", icon: "🎯" },
    { value: "feynman", label: "Feynman", icon: "🧠" },
];

const MODOS_VALIDOS = modos.map((m) => m.value);

const listaUCs = ref(cadeirasDados.map((c) => c.nome_uc));
const mensagens = ref([]);
const mensagemAtual = ref("");
const aCarregar = ref(false);
const chatContainer = ref(null);
const textareaRef = ref(null);
const imagemFicheiro = ref(null);
const imagemPreview = ref(null);
const threadId = ref(crypto.randomUUID());
const ucAtual = ref(listaUCs.value[0] || "Nenhuma UC encontrada");
const isDark = ref(false);
const copiado = ref(null);
const mostrarScrollBtn = ref(false);
const indiceAtivo = ref(-1);
const statusMsg = ref("");
const utilizador = ref(page.props.auth?.user ?? null);
const abortController = ref(null);

const pdfModalAberto = ref(false);
const pdfUrlAtual = ref("");
const pdfFicheiroAtual = ref("");

// AUTO vs MANUAL
const modoManual = ref(null); // null = automático
const modoAuto = ref("default");

const preferenciaAtiva = computed(
    () => modoManual.value ?? modoAuto.value ?? "default",
);

const modoAtual = computed(() =>
    modos.find((m) => m.value === preferenciaAtiva.value),
);

const origemModoLabel = computed(() =>
    modoManual.value ? "Manual" : "Automático",
);

const normalizarTexto = (texto = "") =>
    texto
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^\w\s]/g, " ")
        .replace(/\s+/g, " ")
        .trim();

const PADROES_MODO = {
    quiz: [
        /\bquiz\b/g,
        /\bquestionario\b/g,
        /\bescolha multipla\b/g,
        /\bmultiple choice\b/g,
        /\bverdadeiro ou falso\b/g,
        /\btesta me\b/g,
        /\btesta-me\b/g,
        /\bfaz me perguntas\b/g,
        /\bfaz-me perguntas\b/g,
        /\bperguntas rapidas\b/g,
    ],
    visual: [
        /\bgrafico\b/g,
        /\bgráfico\b/g,
        /\bdiagrama\b/g,
        /\besquema\b/g,
        /\besquema visual\b/g,
        /\bmapa mental\b/g,
        /\bmind map\b/g,
        /\bfluxograma\b/g,
        /\bvisualiza\b/g,
        /\bvisualmente\b/g,
        /\bquadro comparativo\b/g,
        /\btabela comparativa\b/g,
    ],
    plano: [
        /\bplano de estudo\b/g,
        /\bcronograma\b/g,
        /\broteiro de estudo\b/g,
        /\bcalendario\b/g,
        /\bcalendário\b/g,
        /\bagenda de estudo\b/g,
        /\bestudo semanal\b/g,
        /\bestudo por dias\b/g,
        /\borganiza me o estudo\b/g,
        /\borganiza-me o estudo\b/g,
        /\bplaneia o estudo\b/g,
    ],
    feynman: [
        /\bfeynman\b/g,
        /\bmetodo feynman\b/g,
        /\bmétodo feynman\b/g,
        /\bexplica de forma simples\b/g,
        /\bexplica como se eu tivesse 5 anos\b/g,
        /\bavalia a minha explicacao\b/g,
        /\bavalia a minha explicação\b/g,
        /\bcorrige a minha explicacao\b/g,
        /\bcorrige a minha explicação\b/g,
        /\bver se percebi\b/g,
    ],
};

const detetarPreferenciaLocal = (texto) => {
    const t = normalizarTexto(texto);

    if (!t) return "default";

    if (
        /(modo normal|modo tutor|resposta normal|explica normalmente|sem quiz|sem esquema|sem grafico|sem diagrama|volta ao normal|modo automatico normal)/.test(
            t,
        )
    ) {
        return "default";
    }

    const scores = {
        quiz: 0,
        visual: 0,
        plano: 0,
        feynman: 0,
    };

    for (const [modo, regexes] of Object.entries(PADROES_MODO)) {
        for (const regex of regexes) {
            const matches = t.match(regex);
            if (matches) {
                scores[modo] += matches.length;
            }
        }
    }

    let melhorModo = "default";
    let melhorScore = 0;

    for (const modo of ["visual", "quiz", "plano", "feynman"]) {
        if (scores[modo] > melhorScore) {
            melhorModo = modo;
            melhorScore = scores[modo];
        }
    }

    return melhorScore > 0 ? melhorModo : "default";
};

const aplicarPreferenciaAutomatica = (modo) => {
    if (!MODOS_VALIDOS.includes(modo)) return;
    modoAuto.value = modo;
};

const selecionarModoManual = (modo) => {
    if (!MODOS_VALIDOS.includes(modo)) return;

    if (modoManual.value === modo) {
        ativarModoAutomatico();
        return;
    }

    modoManual.value = modo;
};

const ativarModoAutomatico = () => {
    modoManual.value = null;
    modoAuto.value = detetarPreferenciaLocal(mensagemAtual.value);
};

const fecharPdf = () => {
    pdfModalAberto.value = false;
    pdfUrlAtual.value = "";
    pdfFicheiroAtual.value = "";
};

const lidarComCliqueCitacao = (event) => {
    const btn = event.target.closest(".citation-badge");
    if (!btn) return;

    let ficheiro = btn.getAttribute("data-ficheiro")?.trim() || "";
    const pagina = btn.getAttribute("data-pagina")?.trim() || "1";

    ficheiro = ficheiro.replace(/^Ficheiro:\s*/i, "");

    pdfFicheiroAtual.value = ficheiro;
    pdfUrlAtual.value = `http://127.0.0.1:8001/pdfs/${encodeURIComponent(ficheiro)}#page=${pagina}`;
    pdfModalAberto.value = true;
};

const placeholderTexto = computed(() => {
    if (preferenciaAtiva.value === "feynman") {
        return "🧠 Explica o conceito com as tuas palavras... o Tut's vai avaliar-te!";
    }

    if (preferenciaAtiva.value === "quiz") {
        return "🎯 Pede um quiz, verdadeiro/falso ou perguntas rápidas...";
    }

    if (preferenciaAtiva.value === "visual") {
        return "🧩 Pede um esquema, gráfico, diagrama ou mapa mental...";
    }

    if (preferenciaAtiva.value === "plano") {
        return "🗓️ Pede um plano de estudo, cronograma ou organização por dias...";
    }

    return "Faz uma pergunta... (Enter envia, Shift+Enter nova linha)";
});

const sugestoes = computed(() => {
    if (preferenciaAtiva.value === "feynman") {
        return [
            `Explica-me o que é ${ucAtual.value} com as tuas palavras`,
            `Tenta descrever o conceito mais importante desta UC`,
            `Explica-me como se eu tivesse 10 anos`,
            `Começa pelo início — o que sabes sobre este tema?`,
        ];
    }

    if (preferenciaAtiva.value === "quiz") {
        return [
            `Faz-me um quiz sobre ${ucAtual.value}`,
            `Cria 5 perguntas de escolha múltipla`,
            `Testa-me com verdadeiro ou falso`,
            `Faz perguntas e corrige as minhas respostas`,
        ];
    }

    if (preferenciaAtiva.value === "visual") {
        return [
            `Faz-me um esquema visual sobre ${ucAtual.value}`,
            `Cria um diagrama dos conceitos principais`,
            `Organiza isto num mapa mental`,
            `Faz um quadro comparativo dos tópicos`,
        ];
    }

    if (preferenciaAtiva.value === "plano") {
        return [
            `Cria um plano de estudo para ${ucAtual.value}`,
            `Organiza-me o estudo para esta semana`,
            `Faz um cronograma até ao teste`,
            `Divide a matéria por dias`,
        ];
    }

    return [
        `Explica os conceitos base de ${ucAtual.value}`,
        `Cria um resumo dos tópicos mais importantes`,
        `Faz-me um quiz sobre ${ucAtual.value}`,
        `Qual é o melhor plano de estudo?`,
    ];
});

function getCsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : "";
}

function aoAutenticar(user) {
    utilizador.value = user;
}

async function sair() {
    await window.axios.post("/api/logout");
    utilizador.value = null;
    mensagens.value = [];
    statusMsg.value = "";
    threadId.value = crypto.randomUUID();
    ativarModoAutomatico();
}

onMounted(() => {
    const prefereDark =
        localStorage.theme === "dark" ||
        (!("theme" in localStorage) &&
            window.matchMedia("(prefers-color-scheme: dark)").matches);

    isDark.value = prefereDark;
    document.documentElement.classList.toggle("dark", prefereDark);
});

const scrollToBottom = () => {
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
};

const onScroll = () => {
    const el = chatContainer.value;
    if (!el) return;

    mostrarScrollBtn.value =
        el.scrollHeight - el.scrollTop - el.clientHeight > 120;
};

const formatarHora = (d) =>
    d?.toLocaleTimeString("pt-PT", {
        hour: "2-digit",
        minute: "2-digit",
    }) ?? "";

const selecionarUC = (novaUC) => {
    if (ucAtual.value === novaUC) return;

    ucAtual.value = novaUC;
    mensagens.value = [];
    statusMsg.value = "";
    threadId.value = crypto.randomUUID();
};

const limparChat = () => {
    mensagens.value = [];
    statusMsg.value = "";
    threadId.value = crypto.randomUUID();
};

const usarSugestao = (texto) => {
    if (aCarregar.value) return;
    mensagemAtual.value = texto;
    enviarMensagem();
};

const toggleDarkMode = () => {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle("dark", isDark.value);
    localStorage.theme = isDark.value ? "dark" : "light";
    mermaid.initialize({ theme: isDark.value ? "dark" : "base" });
};

const lidarComImagem = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.readAsDataURL(file);

    reader.onload = (event) => {
        const img = new Image();
        img.src = event.target.result;

        img.onload = () => {
            const canvas = document.createElement("canvas");
            const MAX_WIDTH = 1200;
            const MAX_HEIGHT = 1200;

            let width = img.width;
            let height = img.height;

            if (width > height) {
                if (width > MAX_WIDTH) {
                    height *= MAX_WIDTH / width;
                    width = MAX_WIDTH;
                }
            } else if (height > MAX_HEIGHT) {
                width *= MAX_HEIGHT / height;
                height = MAX_HEIGHT;
            }

            canvas.width = width;
            canvas.height = height;

            const ctx = canvas.getContext("2d");
            ctx.drawImage(img, 0, 0, width, height);

            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        imagemFicheiro.value = file;
                        imagemPreview.value = URL.createObjectURL(file);
                        return;
                    }

                    const compressedFile = new File(
                        [blob],
                        file.name.replace(/\.[^/.]+$/, "") + ".jpg",
                        {
                            type: "image/jpeg",
                            lastModified: Date.now(),
                        },
                    );

                    imagemFicheiro.value = compressedFile;
                    imagemPreview.value = URL.createObjectURL(compressedFile);
                },
                "image/jpeg",
                0.8,
            );
        };
    };
};

const removerImagem = () => {
    imagemFicheiro.value = null;
    imagemPreview.value = null;
};

const autoResize = () => {
    const el = textareaRef.value;
    if (!el) return;

    el.style.height = "auto";
    el.style.height = Math.min(el.scrollHeight, 160) + "px";
};

const copiarMensagem = async (content, index) => {
    await navigator.clipboard.writeText(content);
    copiado.value = index;
    setTimeout(() => (copiado.value = null), 2000);
};

const responderQuiz = (msg, pi, oi) => {
    msg.respostas[pi] = oi;
};

const quizFinalizado = (msg) =>
    msg.quiz &&
    msg.respostas &&
    msg.respostas.filter((r) => r !== -1).length === msg.quiz.length;

const pontuacaoQuiz = (msg) =>
    msg.respostas.filter((r, i) => r === msg.quiz[i].correta).length;

const getQuizButtonClass = (msg, qi, oi) => {
    if (msg.respostas[qi] === -1) return "quiz-option--default";
    if (oi === msg.quiz[qi].correta) return "quiz-option--correct";
    if (msg.respostas[qi] === oi) return "quiz-option--wrong";
    return "quiz-option--inactive";
};

const pararGeracao = () => {
    if (!abortController.value) return;

    abortController.value.abort();
    abortController.value = null;
    aCarregar.value = false;
    indiceAtivo.value = -1;
    statusMsg.value = "";

    const lastMsg = mensagens.value[mensagens.value.length - 1];
    if (lastMsg && lastMsg.role === "ai") {
        lastMsg.content += "\n\n*[Geração interrompida]*";
    }
};

const renderMarkdown = (texto, isStreaming = false) => {
    const str = texto || "";
    if (!str.trim()) return "";

    try {
        if (isStreaming) {
            const abertos = (str.match(/```mermaid/g) || []).length;
            const fechados = (str.match(/```mermaid[\s\S]*?```/g) || []).length;

            if (abertos > fechados) {
                const semMermaid = str.replace(/```mermaid[\s\S]*$/, "");
                return DOMPurify.sanitize(
                    marked.parse(semMermaid) +
                        '<div class="mermaid-loading"><span class="mermaid-spinner"></span>A gerar diagrama...</div>',
                    { ADD_ATTR: ["class"], ADD_TAGS: ["div", "span"] },
                );
            }
        }

        let processado = str;

        processado = processado.replace(
            /<think>([\s\S]*?)(?:<\/think>|$)/gi,
            (_, pensamento) => {
                const safePensamento = pensamento.trim();
                if (!safePensamento) return "";

                return `<details class="camaleao-think">
                    <summary class="camaleao-summary">
                        <span class="camaleao-icon">🦎</span>
                        <span class="camaleao-title">Raciocínio em 360º</span>
                        <span class="camaleao-pulse"></span>
                    </summary>
                    <div class="camaleao-content">${safePensamento.replace(/\n/g, "<br>")}</div>
                </details>\n\n`;
            },
        );

        const mermaidBlocks = [];
        processado = processado.replace(
            /```mermaid\s*([\s\S]*?)```/g,
            (_, codigo) => {
                const key = `__MERMAID_BLOCK_${mermaidBlocks.length}__`;
                mermaidBlocks.push(
                    `<div class="mermaid mermaid-block">${codigo.trim()}</div>`,
                );
                return key;
            },
        );

        processado = processado.replace(
            /\[([^\]]+?)\s*:\s*(\d+)\s*\]/g,
            (_, ficheiro, pagina) => {
                const f = ficheiro.trim();
                const p = pagina.trim();

                return `<button class="citation-badge" data-ficheiro="${f}" data-pagina="${p}" title="Abrir Documento Original na página ${p}">
                    <svg class="citation-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    ${f} (Pág. ${p})
                </button>`;
            },
        );

        mermaidBlocks.forEach((block, i) => {
            processado = processado.replace(`__MERMAID_BLOCK_${i}__`, block);
        });

        return DOMPurify.sanitize(marked.parse(processado), {
            ADD_ATTR: ["class", "data-ficheiro", "data-pagina", "title"],
            ADD_TAGS: [
                "svg",
                "path",
                "button",
                "details",
                "summary",
                "div",
                "span",
            ],
        });
    } catch (e) {
        console.error("Erro no renderMarkdown:", e);
        return str;
    }
};

const desenharGraficos = async () => {
    await nextTick();

    try {
        await mermaid.run({
            querySelector: ".mermaid",
            suppressErrors: false,
        });
    } catch (e) {
        console.error("Erro a renderizar Mermaid:", e);
    }
};

const enviarMensagem = async () => {
    if (aCarregar.value) return;
    if (!mensagemAtual.value.trim() && !imagemFicheiro.value) return;

    const textoUser = mensagemAtual.value;
    const imgUser = imagemPreview.value;

    const preferenciaDetetada = detetarPreferenciaLocal(textoUser);

    if (!modoManual.value) {
        aplicarPreferenciaAutomatica(preferenciaDetetada);
    }

    const preferenciaPedido = modoManual.value ?? preferenciaDetetada;

    mensagens.value.push({
        role: "user",
        content: textoUser,
        imagem: imgUser,
        hora: new Date(),
    });

    mensagemAtual.value = "";

    if (textareaRef.value) {
        textareaRef.value.style.height = "auto";
    }

    mensagens.value.push({
        role: "ai",
        content: "",
        sugestoes: [],
        semContexto: false,
        quiz: null,
        respostas: [],
        hora: new Date(),
    });

    const indiceIA = mensagens.value.length - 1;
    indiceAtivo.value = -1;
    statusMsg.value = "";
    aCarregar.value = true;

    await nextTick();
    scrollToBottom();

    const formData = new FormData();
    formData.append("texto", textoUser || "Analisa a imagem em anexo.");
    formData.append("uc", ucAtual.value);
    formData.append("preferencia", preferenciaPedido);

    if (imagemFicheiro.value) {
        formData.append("imagem", imagemFicheiro.value);
    }

    removerImagem();
    abortController.value = new AbortController();

    try {
        const resposta = await fetch("/api/chat/stream", {
            method: "POST",
            credentials: "include",
            headers: {
                Accept: "text/event-stream",
                "X-Requested-With": "XMLHttpRequest",
                "X-XSRF-TOKEN": getCsrfToken(),
            },
            body: formData,
            signal: abortController.value.signal,
        });

        if (resposta.status === 401 || resposta.redirected) {
            utilizador.value = null;
            return;
        }

        if (!resposta.ok) {
            throw new Error(`HTTP ${resposta.status}`);
        }

        const reader = resposta.body.getReader();
        const decoder = new TextDecoder("utf-8");
        let buffer = "";

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const partes = buffer.split("\n");
            buffer = partes.pop();

            for (let parte of partes) {
                parte = parte.trim();
                if (!parte.startsWith("data: ")) continue;

                const jsonStr = parte.substring(6).trim();
                if (jsonStr === "[DONE]") continue;

                try {
                    const data = JSON.parse(jsonStr);

                    if (data.preferencia_auto && !modoManual.value) {
                        aplicarPreferenciaAutomatica(data.preferencia_auto);
                    }

                    if (data.sem_contexto !== undefined) {
                        mensagens.value[indiceIA].semContexto =
                            data.sem_contexto;
                    }

                    if (data.status_msg) {
                        statusMsg.value = data.status_msg;
                        scrollToBottom();
                    }

                    if (data.chunk) {
                        statusMsg.value = "";
                        indiceAtivo.value = indiceIA;
                        mensagens.value[indiceIA].content += data.chunk;
                        scrollToBottom();
                    }
                } catch (e) {
                    // JSON incompleto ou chunk parcial
                }
            }
        }

        indiceAtivo.value = -1;
        statusMsg.value = "";

        let textoIA = mensagens.value[indiceIA].content;

        const sugestoesMatch = textoIA.match(
            /\[SUGEST[OÕ]ES\]([\s\S]*?)\[\/SUGEST[OÕ]ES\]/i,
        );

        if (sugestoesMatch) {
            mensagens.value[indiceIA].sugestoes = sugestoesMatch[1]
                .split("|")
                .map((s) => s.trim())
                .filter(Boolean);
        }

        textoIA = textoIA
            .replace(/\[SUGEST[OÕ]ES\][\s\S]*?\[\/SUGEST[OÕ]ES\]/gi, "")
            .trim();

        const quizMatch = textoIA.match(/\[QUIZ\]([\s\S]*?)\[\/QUIZ\]/i);

        if (quizMatch) {
            try {
                let jsonCru = quizMatch[1].trim();

                jsonCru = jsonCru
                    .replace(/^```(?:json)?\s*/im, "")
                    .replace(/\s*```\s*$/im, "")
                    .trim();

                const arrayMatch = jsonCru.match(/(\[[\s\S]*\])/);
                if (arrayMatch) jsonCru = arrayMatch[1];

                const qData = JSON.parse(jsonCru);

                if (Array.isArray(qData) && qData.length > 0) {
                    mensagens.value[indiceIA].quiz = qData;
                    mensagens.value[indiceIA].respostas = Array(
                        qData.length,
                    ).fill(-1);
                }
            } catch (e) {
                console.warn("Quiz com formato inválido:", e);
            }
        }

        textoIA = textoIA.replace(/\[QUIZ\][\s\S]*?\[\/QUIZ\]/gi, "").trim();
        textoIA = textoIA
            .replace(/\[CALENDARIO\][\s\S]*?\[\/CALENDARIO\]/g, "")
            .trim();

        mensagens.value[indiceIA].content = textoIA;
        await desenharGraficos();
    } catch (error) {
        if (error.name === "AbortError") {
            console.log("⏹️ Geração cancelada pelo aluno.");
        } else {
            indiceAtivo.value = -1;
            statusMsg.value = "";
            mensagens.value[indiceIA].content = `❌ Erro: ${error.message}`;
        }
    } finally {
        aCarregar.value = false;
        abortController.value = null;
        indiceAtivo.value = -1;
        statusMsg.value = "";
        await nextTick();
        scrollToBottom();
    }
};
</script>
<style>
@import url("https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap");
:root {
    --c-bg: #f7f6f3;
    --c-surface: #ffffff;
    --c-surface2: #f0eee9;
    --c-border: #e4e0d8;
    --c-border2: #d4d0c8;
    --c-text: #1a1916;
    --c-text2: #6b6860;
    --c-text3: #9b9890;
    --c-accent: #5b4fe8;
    --c-accent-l: #eae8fc;
    --c-accent2: #e8a24f;
    --c-user-bg: #1a1916;
    --c-user-txt: #f7f6f3;
    --sidebar-w: 260px;
    --radius: 14px;
    --shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 4px 16px rgba(0, 0, 0, 0.04);
    --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.12);
    --font-head: "Syne", sans-serif;
    --font-body: "Instrument Sans", sans-serif;
}
.dark {
    --c-bg: #111110;
    --c-surface: #1c1b1a;
    --c-surface2: #252422;
    --c-border: #2e2c29;
    --c-border2: #3a3835;
    --c-text: #edecea;
    --c-text2: #9a9893;
    --c-text3: #6a6860;
    --c-accent: #7b6ff0;
    --c-accent-l: #1e1c3a;
    --c-accent2: #d4924a;
    --c-user-bg: #7b6ff0;
    --c-user-txt: #ffffff;
}
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
.app-root {
    background: var(--c-bg);
    color: var(--c-text);
    font-family: var(--font-body);
    font-size: 14px;
    line-height: 1.6;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--c-border2);
    border-radius: 20px;
}
.sidebar {
    width: var(--sidebar-w);
    background: var(--c-surface);
    border-right: 1px solid var(--c-border);
    display: flex;
    flex-direction: column;
    transition:
        background 0.3s,
        border-color 0.3s;
}
.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 20px 18px;
    border-bottom: 1px solid var(--c-border);
}
.logo-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--c-accent);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(91, 79, 232, 0.35);
}
.logo-title {
    font-family: var(--font-head);
    font-size: 16px;
    font-weight: 700;
    color: var(--c-text);
    line-height: 1.1;
}
.logo-subtitle {
    font-size: 11px;
    color: var(--c-text3);
    font-weight: 500;
    letter-spacing: 0.03em;
}
.sidebar-section-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--c-text3);
    padding: 16px 20px 8px;
}
.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 4px 10px 10px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.uc-btn {
    display: flex;
    align-items: center;
    gap: 9px;
    width: 100%;
    text-align: left;
    padding: 9px 12px;
    border-radius: 10px;
    border: 1px solid transparent;
    background: transparent;
    cursor: pointer;
    transition: all 0.15s ease;
    color: var(--c-text2);
    font-family: var(--font-body);
    font-size: 13px;
    font-weight: 500;
}
.uc-btn:hover {
    background: var(--c-surface2);
    color: var(--c-text);
}
.uc-btn--active {
    background: var(--c-accent-l);
    border-color: color-mix(in srgb, var(--c-accent) 25%, transparent);
    color: var(--c-accent);
}
.uc-icon {
    font-size: 15px;
    flex-shrink: 0;
}
.uc-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.uc-active-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--c-accent);
    flex-shrink: 0;
}
.sidebar-footer {
    padding: 14px 20px;
    border-top: 1px solid var(--c-border);
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.api-status {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    color: var(--c-text3);
    font-weight: 500;
}
.status-dot {
    position: relative;
    display: flex;
    width: 10px;
    height: 10px;
}
.status-ping {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: #22c55e;
    opacity: 0.6;
    animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;
}
.status-core {
    position: relative;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #22c55e;
}
@keyframes ping {
    0% {
        transform: scale(1);
        opacity: 0.6;
    }
    75%,
    100% {
        transform: scale(2);
        opacity: 0;
    }
}
.user-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.user-name {
    font-size: 12px;
    font-weight: 500;
    color: var(--c-text2);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
}
.logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 7px;
    border: 1px solid var(--c-border);
    background: transparent;
    color: var(--c-text3);
    cursor: pointer;
    transition: all 0.15s;
    flex-shrink: 0;
}
.logout-btn:hover {
    border-color: #ef4444;
    color: #ef4444;
    background: rgba(239, 68, 68, 0.08);
}
.main-area {
    background: var(--c-bg);
    transition: background 0.3s;
}
.app-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 24px;
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    gap: 16px;
    flex-wrap: wrap;
    min-height: 60px;
    flex-shrink: 0;
    transition:
        background 0.3s,
        border-color 0.3s;
}
.header-left {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}
.breadcrumb-chip {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 20px;
    background: var(--c-surface2);
    color: var(--c-text3);
    border: 1px solid var(--c-border);
    flex-shrink: 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.header-uc-name {
    font-family: var(--font-head);
    font-size: 15px;
    font-weight: 700;
    color: var(--c-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.header-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.mode-selector {
    display: flex;
    align-items: center;
    gap: 2px;
    background: var(--c-surface2);
    border: 1px solid var(--c-border);
    border-radius: 10px;
    padding: 3px;
}
.mode-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 7px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-family: var(--font-body);
    font-size: 12px;
    font-weight: 500;
    color: var(--c-text2);
    transition: all 0.15s ease;
    white-space: nowrap;
}
.mode-btn:hover {
    color: var(--c-text);
    background: var(--c-surface);
}
.mode-btn--active {
    background: var(--c-surface);
    color: var(--c-accent);
    box-shadow: var(--shadow);
}
.mode-icon {
    font-size: 13px;
}
.mode-label {
    font-size: 12px;
}
.icon-btn {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    border: 1px solid var(--c-border);
    background: var(--c-surface);
    color: var(--c-text2);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}
.icon-btn:hover {
    border-color: var(--c-border2);
    color: var(--c-text);
    background: var(--c-surface2);
}
.chat-area {
    flex: 1;
    overflow-y: auto;
    padding: 28px 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    position: relative;
    padding-bottom: 80px;
}
.welcome-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 60px 24px;
    flex: 1;
    gap: 16px;
}
.welcome-orb {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: radial-gradient(
        circle at 35% 35%,
        var(--c-accent),
        color-mix(in srgb, var(--c-accent2) 60%, var(--c-accent))
    );
    box-shadow: 0 0 60px color-mix(in srgb, var(--c-accent) 35%, transparent);
    margin-bottom: 8px;
    animation: float 3s ease-in-out infinite;
}
@keyframes float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}
.welcome-title {
    font-family: var(--font-head);
    font-size: 22px;
    font-weight: 700;
    color: var(--c-text);
}
.welcome-subtitle {
    font-size: 14px;
    color: var(--c-text2);
    max-width: 380px;
    line-height: 1.6;
}
.suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
    margin-top: 8px;
    max-width: 520px;
}
.suggestion-chip {
    padding: 8px 16px;
    border-radius: 20px;
    border: 1px solid var(--c-border);
    background: var(--c-surface);
    color: var(--c-text2);
    font-family: var(--font-body);
    font-size: 13px;
    cursor: pointer;
    transition: all 0.15s ease;
    font-weight: 500;
}
.suggestion-chip:hover {
    border-color: var(--c-accent);
    color: var(--c-accent);
    background: var(--c-accent-l);
}
.msg-row {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    animation: msgIn 0.2s ease-out;
}
@keyframes msgIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.msg-row--user {
    flex-direction: row-reverse;
}
.msg-row--ai {
    flex-direction: row;
    flex-wrap: wrap;
}
.msg-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    flex-shrink: 0;
}
.msg-avatar--ai {
    background: var(--c-accent);
    color: white;
    box-shadow: 0 2px 8px color-mix(in srgb, var(--c-accent) 40%, transparent);
}
.msg-avatar--user {
    background: var(--c-surface2);
    color: var(--c-text2);
    border: 1px solid var(--c-border);
}
.msg-content-wrap {
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-width: min(78%, 720px);
}
.msg-row--user .msg-content-wrap {
    align-items: flex-end;
}
.msg-row--ai .msg-content-wrap {
    align-items: flex-start;
}
.msg-bubble {
    padding: 12px 16px;
    border-radius: 16px;
    word-break: break-word;
    transition: background 0.2s;
}
.msg-bubble--user {
    background: var(--c-user-bg);
    color: var(--c-user-txt);
    border-bottom-right-radius: 4px;
    box-shadow: var(--shadow);
}
.msg-bubble--ai {
    background: var(--c-surface);
    color: var(--c-text);
    border: 1px solid var(--c-border);
    border-bottom-left-radius: 4px;
    box-shadow: var(--shadow);
}
.msg-image-wrap {
    margin-bottom: 10px;
}
.msg-image {
    max-width: 200px;
    border-radius: 10px;
    border: 1px solid var(--c-border);
}
.msg-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    opacity: 0;
    transition: opacity 0.15s ease;
    padding: 0 2px;
}
.msg-row:hover .msg-meta {
    opacity: 1;
}
.msg-time {
    font-size: 10px;
    color: var(--c-text3);
}
.copy-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 6px;
    border: 1px solid var(--c-border);
    background: var(--c-surface);
    color: var(--c-text3);
    cursor: pointer;
    font-size: 11px;
    font-weight: 500;
    transition: all 0.15s ease;
}
.copy-btn:hover {
    color: var(--c-text);
    border-color: var(--c-border2);
}
@keyframes blink {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0;
    }
}
.streaming-cursor::after {
    content: "▋";
    display: inline-block;
    color: var(--c-accent);
    animation: blink 0.8s step-end infinite;
    margin-left: 2px;
    font-size: 0.9em;
}
.no-context-alert {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    background: #fef9c3;
    border: 1px solid #fde047;
    color: #854d0e;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.5;
    margin-bottom: 8px;
}
.dark .no-context-alert {
    background: rgba(234, 179, 8, 0.1);
    border-color: rgba(234, 179, 8, 0.3);
    color: #fde047;
}
.ai-suggestions-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}
.ai-suggestion-chip {
    padding: 6px 12px;
    border-radius: 16px;
    border: 1px solid var(--c-accent);
    background: transparent;
    color: var(--c-accent);
    font-family: var(--font-body);
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 500;
}
.ai-suggestion-chip:hover {
    background: var(--c-accent);
    color: white;
}
.typing-bubble {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 16px;
    border-bottom-left-radius: 4px;
    padding: 13px 18px;
    box-shadow: var(--shadow);
    min-width: 60px;
    max-width: 340px;
    transition: all 0.2s ease;
}
.typing-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--c-accent);
    animation: typingBounce 1.2s ease-in-out infinite;
}
.typing-dot:nth-child(2) {
    animation-delay: 0.15s;
}
.typing-dot:nth-child(3) {
    animation-delay: 0.3s;
}
@keyframes typingBounce {
    0%,
    60%,
    100% {
        transform: translateY(0);
        opacity: 0.5;
    }
    30% {
        transform: translateY(-6px);
        opacity: 1;
    }
}
.status-spinner {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    flex-shrink: 0;
    border: 2px solid color-mix(in srgb, var(--c-accent) 25%, transparent);
    border-top-color: var(--c-accent);
    animation: spin 0.7s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
.status-msg-text {
    font-size: 13px;
    font-weight: 500;
    color: var(--c-text2);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    animation: statusFade 0.3s ease-out;
}
@keyframes statusFade {
    from {
        opacity: 0;
        transform: translateX(-4px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
.scroll-fab {
    position: fixed;
    bottom: 100px;
    right: 28px;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--c-surface);
    color: var(--c-text2);
    border: 1px solid var(--c-border);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-lg);
    transition: all 0.15s ease;
    z-index: 50;
}
.scroll-fab:hover {
    background: var(--c-accent);
    color: white;
    border-color: var(--c-accent);
}
.fab-enter-active,
.fab-leave-active {
    transition:
        opacity 0.2s,
        transform 0.2s;
}
.fab-enter-from,
.fab-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
.quiz-container {
    width: 100%;
    margin-top: 12px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.quiz-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.quiz-badge {
    font-size: 12px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
    background: var(--c-accent-l);
    color: var(--c-accent);
    border: 1px solid color-mix(in srgb, var(--c-accent) 20%, transparent);
}
.quiz-score {
    font-size: 12px;
    font-weight: 600;
    color: var(--c-text2);
}
.quiz-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: var(--radius);
    padding: 18px 20px;
    box-shadow: var(--shadow);
}
.quiz-question {
    font-weight: 600;
    color: var(--c-text);
    font-size: 14px;
    margin-bottom: 14px;
    display: flex;
    gap: 10px;
    align-items: flex-start;
}
.quiz-q-num {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--c-accent-l);
    color: var(--c-accent);
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
    margin-top: 1px;
}
.quiz-options {
    display: flex;
    flex-direction: column;
    gap: 7px;
}
.quiz-option {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    text-align: left;
    padding: 10px 14px;
    border-radius: 9px;
    border: 1px solid var(--c-border);
    background: var(--c-surface2);
    color: var(--c-text);
    cursor: pointer;
    font-family: var(--font-body);
    font-size: 13px;
    font-weight: 500;
    transition: all 0.15s ease;
}
.quiz-option-letter {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
    color: var(--c-text2);
}
.quiz-option--default:hover {
    border-color: var(--c-accent);
    background: var(--c-accent-l);
    color: var(--c-accent);
}
.quiz-option--correct {
    background: #f0fdf4;
    border-color: #22c55e;
    color: #15803d;
}
.dark .quiz-option--correct {
    background: rgba(34, 197, 94, 0.12);
    border-color: #16a34a;
    color: #4ade80;
}
.quiz-option--wrong {
    background: #fef2f2;
    border-color: #ef4444;
    color: #b91c1c;
}
.dark .quiz-option--wrong {
    background: rgba(239, 68, 68, 0.12);
    border-color: #dc2626;
    color: #f87171;
}
.quiz-option--inactive {
    opacity: 0.4;
    cursor: not-allowed;
}
.quiz-feedback {
    margin-top: 10px;
    padding: 9px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    animation: msgIn 0.2s ease-out;
}
.quiz-feedback--correct {
    background: #f0fdf4;
    color: #15803d;
}
.dark .quiz-feedback--correct {
    background: rgba(34, 197, 94, 0.12);
    color: #4ade80;
}
.quiz-feedback--wrong {
    background: #fef2f2;
    color: #b91c1c;
}
.dark .quiz-feedback--wrong {
    background: rgba(239, 68, 68, 0.12);
    color: #f87171;
}
.quiz-explanation {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid color-mix(in srgb, currentColor 20%, transparent);
    font-size: 12px;
    line-height: 1.5;
}
.quiz-explain-btn {
    margin-top: 8px;
    padding: 6px 12px;
    border-radius: 6px;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    color: var(--c-text2);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
}
.quiz-explain-btn:hover {
    background: var(--c-accent-l);
    color: var(--c-accent);
    border-color: var(--c-accent);
}
.app-footer {
    padding: 14px 24px 16px;
    background: var(--c-surface);
    border-top: 1px solid var(--c-border);
    flex-shrink: 0;
    transition:
        background 0.3s,
        border-color 0.3s;
}
.image-preview-wrap {
    position: relative;
    display: inline-block;
    margin-bottom: 10px;
}
.image-preview-thumb {
    width: 56px;
    height: 56px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid var(--c-border);
    display: block;
}
.image-preview-remove {
    position: absolute;
    top: -6px;
    right: -6px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #ef4444;
    color: white;
    border: none;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}
.input-area {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    background: var(--c-bg);
    border: 1.5px solid var(--c-border);
    border-radius: 14px;
    padding: 6px 8px 6px 6px;
    transition: border-color 0.2s;
}
.input-area:focus-within {
    border-color: var(--c-accent);
    background: var(--c-surface);
}
.attach-btn {
    padding: 8px;
    border-radius: 8px;
    color: var(--c-text3);
    cursor: pointer;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.attach-btn:hover {
    color: var(--c-accent);
    background: var(--c-accent-l);
}
.chat-textarea {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    resize: none;
    font-family: var(--font-body);
    font-size: 14px;
    color: var(--c-text);
    padding: 7px 4px;
    max-height: 160px;
    min-height: 36px;
    line-height: 1.5;
}
.chat-textarea::placeholder {
    color: var(--c-text3);
}
.input-right {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.char-hint {
    font-size: 11px;
    color: var(--c-text3);
    font-weight: 500;
    min-width: 24px;
    text-align: right;
}
.send-btn {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    border: none;
    background: var(--c-border2);
    color: var(--c-text3);
    cursor: not-allowed;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.send-btn--active {
    background: var(--c-accent);
    color: white;
    cursor: pointer;
    box-shadow: 0 2px 8px color-mix(in srgb, var(--c-accent) 40%, transparent);
}
.send-btn--active:hover {
    filter: brightness(1.1);
}
.send-btn:disabled:not(.send-btn--active) {
    opacity: 0.5;
}
.input-hint {
    font-size: 11px;
    color: var(--c-text3);
    margin-top: 7px;
    padding: 0 4px;
}
.prose-content {
    font-family: var(--font-body);
    font-size: 14px;
    line-height: 1.65;
}
.prose-content p {
    margin-bottom: 0.75em;
}
.prose-content p:last-child {
    margin-bottom: 0;
}
.prose-content ul {
    list-style: disc;
    padding-left: 1.4em;
    margin-bottom: 0.85em;
}
.prose-content ol {
    list-style: decimal;
    padding-left: 1.4em;
    margin-bottom: 0.85em;
}
.prose-content li {
    margin-bottom: 0.25em;
}
.prose-content h1,
.prose-content h2,
.prose-content h3 {
    font-family: var(--font-head);
    font-weight: 700;
    margin-top: 1.3em;
    margin-bottom: 0.4em;
    line-height: 1.2;
}
.prose-content h1 {
    font-size: 1.25em;
}
.prose-content h2 {
    font-size: 1.1em;
}
.prose-content h3 {
    font-size: 1em;
}
.prose-content strong {
    font-weight: 600;
}
.prose-content em {
    font-style: italic;
}
.prose-content blockquote {
    border-left: 3px solid var(--c-accent);
    padding-left: 12px;
    color: var(--c-text2);
    margin: 0.75em 0;
}
.prose-content code {
    background: var(--c-surface2);
    border: 1px solid var(--c-border);
    padding: 0.15em 0.4em;
    border-radius: 5px;
    font-size: 0.85em;
    font-family: "Fira Code", "Cascadia Code", monospace;
    color: var(--c-accent);
}
.prose-content pre {
    background: var(--c-surface2);
    border: 1px solid var(--c-border);
    border-radius: 10px;
    padding: 14px 16px;
    overflow-x: auto;
    margin: 0.85em 0;
}
.prose-content pre code {
    background: none;
    border: none;
    padding: 0;
    color: var(--c-text);
    font-size: 0.875em;
}
.prose-content a {
    color: var(--c-accent);
    text-decoration: underline;
    text-underline-offset: 2px;
}
.prose-content hr {
    border: none;
    border-top: 1px solid var(--c-border);
    margin: 1em 0;
}
.prose-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 0.85em 0;
    font-size: 13px;
}
.prose-content th {
    background: var(--c-surface2);
    padding: 8px 12px;
    text-align: left;
    font-weight: 600;
    border: 1px solid var(--c-border);
}
.prose-content td {
    padding: 8px 12px;
    border: 1px solid var(--c-border);
}
.prose-user * {
    color: inherit;
}
.prose-user code {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.2);
    color: inherit;
}
.stop-btn {
    position: absolute;
    bottom: 120px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    color: var(--c-text);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: var(--shadow-lg);
    z-index: 20;
    transition: all 0.2s ease;
}
.stop-btn:hover {
    border-color: var(--c-accent);
    color: var(--c-accent);
}
.fade-up-enter-active,
.fade-up-leave-active {
    transition: all 0.3s ease;
}
.fade-up-enter-from,
.fade-up-leave-to {
    opacity: 0;
    transform: translate(-50%, 10px);
}
:deep(.camaleao-think) {
    background: var(--c-surface2);
    border: 1px solid var(--c-border);
    border-radius: 12px;
    margin: 16px 0;
    overflow: hidden;
    transition: all 0.3s ease;
}
:deep(.camaleao-summary) {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    cursor: pointer;
    user-select: none;
    font-size: 13px;
    font-weight: 600;
    color: var(--c-text2);
    background: var(--c-surface);
    transition: background 0.2s ease;
    list-style: none;
}
:deep(.camaleao-summary::-webkit-details-marker) {
    display: none;
}
:deep(.camaleao-summary:hover) {
    background: var(--c-surface2);
    color: var(--c-accent);
}
:deep(.camaleao-pulse) {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--c-accent2);
    animation: livePulse 1.5s infinite;
}
:deep(.camaleao-content) {
    padding: 16px;
    font-family: "Fira Code", monospace;
    font-size: 12px;
    color: var(--c-text3);
    line-height: 1.6;
    border-top: 1px dashed var(--c-border);
    background: rgba(0, 0, 0, 0.03);
}
.mermaid-block {
    display: flex;
    justify-content: center;
    margin: 16px 0;
    background: var(--c-surface2);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid var(--c-border);
}
.mermaid-loading {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px;
    border-radius: 12px;
    border: 1px dashed var(--c-border2);
    background: var(--c-surface2);
    color: var(--c-text3);
    font-size: 13px;
    font-weight: 500;
    margin: 16px 0;
}
.mermaid-spinner {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid color-mix(in srgb, var(--c-accent) 25%, transparent);
    border-top-color: var(--c-accent);
    animation: spin 0.7s linear infinite;
    flex-shrink: 0;
}
.citation-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--c-surface2);
    border: 1px solid var(--c-border2);
    color: var(--c-text2);
    font-size: 11px;
    font-family: var(--font-body);
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 12px;
    margin: 0 4px;
    cursor: pointer;
    transition: all 0.15s ease;
    vertical-align: middle;
}
.citation-badge:hover {
    background: var(--c-accent-l);
    border-color: var(--c-accent);
    color: var(--c-accent);
    transform: translateY(-1px);
}
.citation-icon {
    width: 12px;
    height: 12px;
}
.pdf-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
}
.pdf-modal-container {
    background: var(--c-surface);
    width: 90%;
    max-width: 1200px;
    height: 90vh;
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.pdf-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px;
    border-bottom: 1px solid var(--c-border);
    background: var(--c-surface2);
}
.pdf-modal-title {
    font-family: var(--font-head);
    font-weight: 700;
    font-size: 16px;
    color: var(--c-text);
    display: flex;
    align-items: center;
    gap: 12px;
}
.pdf-modal-filename {
    font-family: var(--font-body);
    font-weight: 500;
    font-size: 13px;
    color: var(--c-text2);
    background: var(--c-surface);
    padding: 2px 10px;
    border-radius: 20px;
    border: 1px solid var(--c-border);
}
.pdf-modal-close {
    background: transparent;
    border: none;
    color: var(--c-text2);
    cursor: pointer;
    padding: 4px;
    border-radius: 6px;
    transition: all 0.15s ease;
}
.pdf-modal-close:hover {
    background: #ef4444;
    color: white;
}
.pdf-modal-iframe {
    flex: 1;
    width: 100%;
    border: none;
    background: #e5e7eb;
}
@keyframes modalIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
.mode-btn--manual {
    border: 1px solid color-mix(in srgb, var(--c-accent) 35%, transparent);
}

.mode-source-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 88px;
    height: 34px;
    padding: 0 12px;
    border-radius: 999px;
    border: 1px solid var(--c-border);
    background: var(--c-surface);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    white-space: nowrap;
}

.mode-source-chip--auto {
    color: var(--c-accent);
    background: var(--c-accent-l);
    border-color: color-mix(in srgb, var(--c-accent) 25%, transparent);
}

.mode-source-chip--manual {
    color: #d97706;
    background: rgba(217, 119, 6, 0.1);
    border-color: rgba(217, 119, 6, 0.28);
}

.dark .mode-source-chip--manual {
    color: #fbbf24;
    background: rgba(251, 191, 36, 0.12);
    border-color: rgba(251, 191, 36, 0.22);
}
</style>
