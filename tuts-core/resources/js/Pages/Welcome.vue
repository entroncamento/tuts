<template>
    <div
        class="app-root flex h-screen font-sans transition-colors duration-300"
        :class="{ dark: isDark }"
    >
        <!-- ═══════════════════════════════════════ SIDEBAR ═══════════════════════════════════════ -->
        <aside class="sidebar flex flex-col flex-shrink-0 z-10">
            <!-- Logo -->
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

            <!-- UC List -->
            <div class="sidebar-section-label">As Tuas Cadeiras</div>
            <nav class="sidebar-nav custom-scrollbar">
                <button
                    v-for="uc in listaUCs"
                    :key="uc"
                    @click="selecionarUC(uc)"
                    :class="['uc-btn', ucAtual === uc ? 'uc-btn--active' : '']"
                    :title="uc"
                >
                    <span class="uc-icon">{{
                        ucAtual === uc ? "📂" : "📁"
                    }}</span>
                    <span class="uc-name">{{ uc }}</span>
                    <span v-if="ucAtual === uc" class="uc-active-dot"></span>
                </button>
            </nav>

            <!-- Status Footer -->
            <div class="sidebar-footer">
                <div class="api-status">
                    <span class="status-dot">
                        <span class="status-ping"></span>
                        <span class="status-core"></span>
                    </span>
                    <span>API Online · Porta 8001</span>
                </div>
            </div>
        </aside>

        <!-- ════════════════════════════════════════ MAIN ════════════════════════════════════════ -->
        <div class="main-area flex flex-col flex-1 h-screen overflow-hidden">
            <!-- Header -->
            <header class="app-header">
                <div class="header-left">
                    <div class="breadcrumb-chip">A estudar</div>
                    <h2 class="header-uc-name">{{ ucAtual }}</h2>
                </div>

                <div class="header-right">
                    <!-- Mode Selector -->
                    <div class="mode-selector">
                        <button
                            v-for="m in modos"
                            :key="m.value"
                            @click="preferencia = m.value"
                            :class="[
                                'mode-btn',
                                preferencia === m.value
                                    ? 'mode-btn--active'
                                    : '',
                            ]"
                            :title="m.label"
                        >
                            <span class="mode-icon">{{ m.icon }}</span>
                            <span class="mode-label">{{ m.label }}</span>
                        </button>
                    </div>

                    <!-- Dark Mode Toggle -->
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

                    <!-- Clear Chat -->
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

            <!-- Chat Area -->
            <main class="chat-area custom-scrollbar" ref="chatContainer">
                <!-- Welcome State -->
                <div v-if="mensagens.length === 0" class="welcome-state">
                    <div class="welcome-orb"></div>
                    <h3 class="welcome-title">Olá! Sou o Tut's 👋</h3>
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

                <!-- Messages -->
                <div
                    v-for="(msg, index) in mensagens"
                    :key="index"
                    :class="[
                        'msg-row',
                        msg.role === 'user' ? 'msg-row--user' : 'msg-row--ai',
                    ]"
                >
                    <!-- AI Avatar -->
                    <div
                        v-if="msg.role === 'ai'"
                        class="msg-avatar msg-avatar--ai"
                    >
                        T
                    </div>

                    <div class="msg-content-wrap">
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
                                :class="
                                    msg.role === 'user'
                                        ? 'prose-user'
                                        : 'prose-ai'
                                "
                                v-html="renderMarkdown(msg.content)"
                            ></div>
                        </div>

                        <!-- Copy button for AI -->
                        <button
                            v-if="msg.role === 'ai'"
                            @click="copiarMensagem(msg.content, index)"
                            class="copy-btn"
                            :title="copiado === index ? 'Copiado!' : 'Copiar'"
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
                                class="w-3.5 h-3.5 text-green-500"
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
                        </button>
                    </div>

                    <!-- User Avatar -->
                    <div
                        v-if="msg.role === 'user'"
                        class="msg-avatar msg-avatar--user"
                    >
                        U
                    </div>

                    <!-- Quiz Cards -->
                    <div
                        v-if="msg.quiz && msg.quiz.length > 0"
                        class="quiz-container"
                    >
                        <div class="quiz-header">
                            <span class="quiz-badge">🎮 Quiz Interativo</span>
                            <span class="quiz-score" v-if="quizFinalizado(msg)">
                                {{ pontuacaoQuiz(msg) }}/{{
                                    msg.quiz.length
                                }}
                                corretas
                            </span>
                        </div>
                        <div
                            v-for="(pergunta, qIndex) in msg.quiz"
                            :key="qIndex"
                            class="quiz-card"
                        >
                            <p class="quiz-question">
                                <span class="quiz-q-num">{{ qIndex + 1 }}</span>
                                {{ pergunta.pergunta }}
                            </p>
                            <div class="quiz-options">
                                <button
                                    v-for="(opcao, oIndex) in pergunta.opcoes"
                                    :key="oIndex"
                                    @click="responderQuiz(msg, qIndex, oIndex)"
                                    :disabled="
                                        msg.respostas[qIndex] !== undefined
                                    "
                                    :class="[
                                        'quiz-option',
                                        getQuizButtonClass(msg, qIndex, oIndex),
                                    ]"
                                >
                                    <span class="quiz-option-letter">{{
                                        String.fromCharCode(65 + oIndex)
                                    }}</span>
                                    <span>{{ opcao }}</span>
                                </button>
                            </div>
                            <div
                                v-if="msg.respostas[qIndex] !== undefined"
                                :class="[
                                    'quiz-feedback',
                                    msg.respostas[qIndex] === pergunta.correta
                                        ? 'quiz-feedback--correct'
                                        : 'quiz-feedback--wrong',
                                ]"
                            >
                                <span
                                    v-if="
                                        msg.respostas[qIndex] ===
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
                        </div>
                    </div>
                </div>

                <!-- Loading Indicator -->
                <div v-if="aCarregar" class="msg-row msg-row--ai">
                    <div class="msg-avatar msg-avatar--ai">T</div>
                    <div class="typing-bubble">
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                    </div>
                </div>
            </main>

            <!-- Input Footer -->
            <footer class="app-footer">
                <!-- Image Preview -->
                <div v-if="imagemPreview" class="image-preview-wrap">
                    <img :src="imagemPreview" class="image-preview-thumb" />
                    <button @click="removerImagem" class="image-preview-remove">
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
                        @keydown.shift.enter="novaLinha"
                        @input="autoResize"
                        ref="textareaRef"
                        rows="1"
                        placeholder="Faz uma pergunta... (Enter para enviar, Shift+Enter para nova linha)"
                        class="chat-textarea"
                    ></textarea>

                    <div class="input-right">
                        <span class="char-hint" v-if="mensagemAtual.length > 0">
                            {{ mensagemAtual.length }}
                        </span>
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
                                    d="M5 12h14M12 5l7 7-7 7"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
                <p class="input-hint">
                    Modo atual: <strong>{{ modoAtual?.label }}</strong> · Enter
                    envia · Shift+Enter nova linha
                </p>
            </footer>
        </div>
    </div>
</template>

<script setup>
import { ref, nextTick, onMounted, computed } from "vue";
import axios from "axios";
import { marked } from "marked";
import DOMPurify from "dompurify";
import mermaid from "mermaid";
import cadeirasDados from "../cadeiras_mtc.json";

mermaid.initialize({ startOnLoad: false, theme: "base" });

const modos = [
    { value: "textual", icon: "📖", label: "Tutor" },
    { value: "visual", icon: "🎨", label: "Visual" },
    { value: "plano", icon: "📅", label: "Plano" },
    { value: "quiz", icon: "🎮", label: "Quiz" },
    { value: "feynman", icon: "🧠", label: "Feynman" },
];

const listaUCs = ref(cadeirasDados.map((c) => c.nome_uc));
const mensagens = ref([]);
const mensagemAtual = ref("");
const aCarregar = ref(false);
const chatContainer = ref(null);
const textareaRef = ref(null);
const preferencia = ref("textual");
const imagemFicheiro = ref(null);
const imagemPreview = ref(null);
const threadId = ref(crypto.randomUUID());
const ucAtual = ref(listaUCs.value[0] || "Nenhuma UC encontrada");
const isDark = ref(false);
const copiado = ref(null);

const modoAtual = computed(() =>
    modos.find((m) => m.value === preferencia.value),
);

const sugestoes = computed(() => [
    `Explica os conceitos base de ${ucAtual.value}`,
    `Cria um resumo dos tópicos mais importantes`,
    `Faz-me um quiz sobre ${ucAtual.value}`,
    `Qual é o melhor plano de estudo?`,
]);

const selecionarUC = (novaUC) => {
    if (ucAtual.value === novaUC) return;
    ucAtual.value = novaUC;
    mensagens.value = [];
    threadId.value = crypto.randomUUID();
};

const limparChat = () => {
    mensagens.value = [];
    threadId.value = crypto.randomUUID();
};

const usarSugestao = (texto) => {
    mensagemAtual.value = texto;
    enviarMensagem();
};

onMounted(() => {
    const prefereDark =
        localStorage.theme === "dark" ||
        (!("theme" in localStorage) &&
            window.matchMedia("(prefers-color-scheme: dark)").matches);
    isDark.value = prefereDark;
    document.documentElement.classList.toggle("dark", prefereDark);
});

const toggleDarkMode = () => {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle("dark", isDark.value);
    localStorage.theme = isDark.value ? "dark" : "light";
    mermaid.initialize({ theme: isDark.value ? "dark" : "base" });
};

const lidarComImagem = (event) => {
    const file = event.target.files[0];
    if (!file) return;
    imagemFicheiro.value = file;
    imagemPreview.value = URL.createObjectURL(file);
};

const removerImagem = () => {
    imagemFicheiro.value = null;
    imagemPreview.value = null;
};

const novaLinha = () => {
    // Shift+Enter: add newline naturally (default behavior kept)
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

// Quiz helpers
const responderQuiz = (msg, perguntaIndex, opcaoIndex) => {
    msg.respostas[perguntaIndex] = opcaoIndex;
};

const quizFinalizado = (msg) =>
    msg.quiz &&
    msg.respostas &&
    msg.respostas.filter((r) => r !== undefined).length === msg.quiz.length;

const pontuacaoQuiz = (msg) =>
    msg.respostas.filter((r, i) => r === msg.quiz[i].correta).length;

const getQuizButtonClass = (msg, qIndex, oIndex) => {
    const respondido = msg.respostas[qIndex] !== undefined;
    const correta = msg.quiz[qIndex].correta;
    if (!respondido) return "quiz-option--default";
    if (oIndex === correta) return "quiz-option--correct";
    if (msg.respostas[qIndex] === oIndex && oIndex !== correta)
        return "quiz-option--wrong";
    return "quiz-option--inactive";
};

const renderMarkdown = (texto) => {
    const str = texto || "";
    if (!str.trim()) return "";
    try {
        const comMermaid = str.replace(
            /```mermaid\n([\s\S]*?)```/g,
            (match, codigo) => {
                const safe = codigo
                    .replace(/\[.*?:\d+\]/g, "")
                    .replace(/\[|\]/g, "");
                return `<div class="mermaid mermaid-block">${safe}</div>`;
            },
        );
        return DOMPurify.sanitize(marked.parse(comMermaid), {
            ADD_ATTR: ["class"],
        });
    } catch (e) {
        return str;
    }
};

const desenharGraficos = async () => {
    await nextTick();
    try {
        await mermaid.run({ querySelector: ".mermaid", suppressErrors: true });
    } catch (e) {
        /* suppress */
    }
};

const enviarMensagem = async () => {
    if (!mensagemAtual.value.trim() && !imagemFicheiro.value) return;

    const textoUser = mensagemAtual.value;
    const imgUser = imagemPreview.value;

    mensagens.value.push({ role: "user", content: textoUser, imagem: imgUser });
    mensagemAtual.value = "";
    if (textareaRef.value) textareaRef.value.style.height = "auto";
    aCarregar.value = true;

    await nextTick();
    scrollToBottom();

    const formData = new FormData();
    formData.append("texto", textoUser || "Analisa a imagem em anexo.");
    formData.append("thread_id", threadId.value);
    formData.append("uc", ucAtual.value);
    formData.append("preferencia", preferencia.value);
    formData.append("historico", JSON.stringify([]));
    if (imagemFicheiro.value) formData.append("imagem", imagemFicheiro.value);
    removerImagem();

    try {
        const resposta = await axios.post(
            "http://127.0.0.1:8001/perguntar",
            formData,
            {
                headers: { "Content-Type": "multipart/form-data" },
            },
        );

        if (resposta.data.thread_id) threadId.value = resposta.data.thread_id;

        let textoIA =
            resposta.data.resposta_stu ||
            resposta.data.mensagem ||
            "⚠️ Resposta não reconhecida.";
        let quizData = null;

        const quizMatch = textoIA.match(/\[QUIZ\]([\s\S]*?)\[\/QUIZ\]/);
        if (quizMatch) {
            try {
                quizData = JSON.parse(quizMatch[1].trim());
                textoIA = textoIA
                    .replace(/\[QUIZ\][\s\S]*?\[\/QUIZ\]/, "")
                    .trim();
            } catch (e) {
                console.error("Quiz parse error:", e);
            }
        }

        mensagens.value.push({
            role: "ai",
            content: textoIA,
            quiz: quizData,
            respostas: quizData ? new Array(quizData.length) : [],
        });

        await desenharGraficos();
    } catch (error) {
        mensagens.value.push({
            role: "ai",
            content:
                "❌ Erro de comunicação com o Backend. Confirma que a API está em execução na porta 8001.",
        });
    } finally {
        aCarregar.value = false;
        await nextTick();
        scrollToBottom();
    }
};

const scrollToBottom = () => {
    if (chatContainer.value)
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
};
</script>

<style>
/* ─── Google Fonts ─── */
@import url("https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap");

/* ─── CSS Variables ─── */
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
    --c-user-text: #f7f6f3;
    --sidebar-w: 260px;
    --radius: 14px;
    --shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 4px 16px rgba(0, 0, 0, 0.04);
    --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.1);
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
    --c-user-text: #ffffff;
}

/* ─── Base ─── */
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

/* ─── Scrollbar ─── */
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

/* ─── SIDEBAR ─── */
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
    position: relative;
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

/* ─── MAIN AREA ─── */
.main-area {
    background: var(--c-bg);
    transition: background 0.3s;
}

/* ─── HEADER ─── */
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

/* Mode Selector */
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

/* Icon Buttons */
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

/* ─── CHAT AREA ─── */
.chat-area {
    flex: 1;
    overflow-y: auto;
    padding: 28px 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* ─── WELCOME STATE ─── */
.welcome-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 60px 24px;
    flex: 1;
    gap: 16px;
    position: relative;
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

/* ─── MESSAGES ─── */
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
    flex-shrink: 0;
    letter-spacing: 0.01em;
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
    color: var(--c-user-text);
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

.copy-btn {
    align-self: flex-start;
    padding: 4px 8px;
    border-radius: 6px;
    border: 1px solid var(--c-border);
    background: var(--c-surface);
    color: var(--c-text3);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 500;
    transition: all 0.15s ease;
    opacity: 0;
}

.msg-row:hover .copy-btn {
    opacity: 1;
}
.copy-btn:hover {
    color: var(--c-text);
    border-color: var(--c-border2);
}

/* ─── TYPING INDICATOR ─── */
.typing-bubble {
    display: flex;
    align-items: center;
    gap: 5px;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 16px;
    border-bottom-left-radius: 4px;
    padding: 14px 18px;
    box-shadow: var(--shadow);
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

/* ─── QUIZ ─── */
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
    transition: background 0.2s;
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

/* ─── FOOTER ─── */
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
    line-height: 1;
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

/* ─── PROSE STYLES ─── */
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

.mermaid-block {
    display: flex;
    justify-content: center;
    margin: 16px 0;
    background: var(--c-surface2);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid var(--c-border);
}
</style>
