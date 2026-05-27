<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { BookOpen, Send } from "@lucide/vue";

import MarkdownMessage from "@/app/components/MarkdownMessage.vue";
import CitationPdfModal from "@/app/components/CitationPdfModal.vue";
import { fetchMySubjects } from "@/app/services/subjects";
import { apiFetch } from "@/app/services/api";
import { type UCData, UC_LIST } from "@/app/data/ucData";
import {
    useTutsChat,
    type TutsChatMessage,
} from "@/app/composables/useTutsChat";

const route = useRoute();
const router = useRouter();

const ucs = ref<UCData[]>(UC_LIST);
const selectedUcId = ref<string>("");
const inputValue = ref("");
const chatEndRef = ref<HTMLDivElement | null>(null);
const loadingHistory = ref(false);

const citationModalOpen = ref(false);
const citationFile = ref("");
const citationPage = ref(1);

const {
    messages,
    isStreaming,
    error,
    chatId,
    lastStatus,
    sendMessage,
    setChatId,
    setMessages,
    clearMessages,
} = useTutsChat();

const selectedUc = computed(
    () => ucs.value.find((uc) => uc.id === selectedUcId.value) ?? null,
);

const canSend = computed(() => {
    return (
        !isStreaming.value && !!inputValue.value.trim() && !!selectedUc.value
    );
});

function convertBackendMessage(message: {
    id: number;
    role: "user" | "ai";
    content: string;
}): TutsChatMessage {
    return {
        id: String(message.id),
        role: message.role === "ai" ? "assistant" : "user",
        content: message.content,
    };
}

function openCitation(payload: { file: string; page: number }) {
    citationFile.value = payload.file;
    citationPage.value = payload.page;
    citationModalOpen.value = true;
}

function closeCitation() {
    citationModalOpen.value = false;
}

async function loadInitialState() {
    ucs.value = await fetchMySubjects();

    const queryUc = typeof route.query.uc === "string" ? route.query.uc : "";
    const queryChatId =
        typeof route.query.chat_id === "string"
            ? Number(route.query.chat_id)
            : null;

    if (queryUc) {
        const match = ucs.value.find((uc) => uc.name === queryUc);
        if (match) selectedUcId.value = match.id;
    }

    if (!selectedUcId.value && ucs.value.length > 0) {
        selectedUcId.value = ucs.value[0].id;
    }

    if (queryChatId && Number.isFinite(queryChatId)) {
        await loadChat(queryChatId);
    }
}

async function loadChat(id: number) {
    loadingHistory.value = true;

    try {
        const response = await apiFetch<{
            status: string;
            chat_id: number;
            titulo: string;
            mensagens: Array<{
                id: number;
                role: "user" | "ai";
                content: string;
            }>;
        }>(`/api/chat/${id}`);

        setChatId(response.chat_id);
        setMessages(response.mensagens.map(convertBackendMessage));
    } finally {
        loadingHistory.value = false;
    }
}

async function handleSend() {
    const text = inputValue.value.trim();

    if (!text || !selectedUc.value || isStreaming.value) return;

    inputValue.value = "";

    await sendMessage({
        message: text,
        ucName: selectedUc.value.name,
    });
}

async function newConversation() {
    clearMessages();
    setChatId(null);
    inputValue.value = "";

    await router.replace({
        path: "/chat",
        query: selectedUc.value
            ? {
                  uc: selectedUc.value.name,
              }
            : {},
    });
}

watch(chatId, async (newChatId) => {
    if (!newChatId) return;

    const currentChatId =
        typeof route.query.chat_id === "string"
            ? Number(route.query.chat_id)
            : null;

    if (currentChatId === Number(newChatId)) return;

    const query: Record<string, string> = {
        chat_id: String(newChatId),
    };

    if (selectedUc.value?.name) {
        query.uc = selectedUc.value.name;
    }

    await router.replace({
        path: "/chat",
        query,
    });
});

watch(
    messages,
    async () => {
        await nextTick();
        chatEndRef.value?.scrollIntoView({ behavior: "smooth" });
    },
    { deep: true },
);

onMounted(loadInitialState);
</script>

<template>
    <div
        style="
            height: 100%;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            font-family: Inter, sans-serif;
        "
    >
        <div
            style="
                max-width: 920px;
                width: 100%;
                margin: 0 auto;
                padding: 24px 24px 0;
                box-sizing: border-box;
            "
        >
            <div
                style="
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 16px;
                    margin-bottom: 18px;
                "
            >
                <div>
                    <h1
                        style="
                            font-size: 24px;
                            font-weight: 700;
                            color: #1e1e1e;
                            margin: 0 0 4px;
                        "
                    >
                        Chat TUT'S
                    </h1>

                    <p style="font-size: 13px; color: #9e9e9e; margin: 0">
                        Ligado ao backend Laravel e ao serviço RAG.
                    </p>
                </div>

                <button
                    style="
                        border: 1px solid #e5e5e5;
                        background: #ffffff;
                        border-radius: 10px;
                        padding: 9px 14px;
                        cursor: pointer;
                        color: #656966;
                        font-weight: 600;
                    "
                    @click="newConversation"
                >
                    Nova conversa
                </button>
            </div>

            <div
                style="
                    display: flex;
                    gap: 12px;
                    align-items: center;
                    margin-bottom: 18px;
                "
            >
                <BookOpen :size="17" color="#009957" />

                <select
                    v-model="selectedUcId"
                    style="
                        flex: 1;
                        border: 1px solid #e5e5e5;
                        border-radius: 10px;
                        padding: 10px 12px;
                        font-family: Inter, sans-serif;
                        outline: none;
                    "
                >
                    <option value="" disabled>Escolhe uma UC</option>

                    <option v-for="uc in ucs" :key="uc.id" :value="uc.id">
                        {{ uc.name }}
                    </option>
                </select>
            </div>
        </div>

        <div style="flex: 1; overflow-y: auto">
            <div
                style="
                    max-width: 920px;
                    margin: 0 auto;
                    padding: 20px 24px 130px;
                    display: flex;
                    flex-direction: column;
                    gap: 18px;
                    box-sizing: border-box;
                "
            >
                <div
                    v-if="messages.length === 0 && !loadingHistory"
                    style="
                        min-height: 360px;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        text-align: center;
                    "
                >
                    <div
                        style="
                            width: 54px;
                            height: 54px;
                            border-radius: 16px;
                            background: rgba(0, 153, 87, 0.08);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin-bottom: 18px;
                        "
                    >
                        <BookOpen :size="26" color="#009957" />
                    </div>

                    <h2
                        style="font-size: 22px; color: #1e1e1e; margin: 0 0 8px"
                    >
                        Estudar com o TUT'S
                    </h2>

                    <p
                        style="
                            font-size: 14px;
                            color: #9e9e9e;
                            margin: 0;
                            max-width: 420px;
                            line-height: 1.5;
                        "
                    >
                        Escolhe a UC e faz uma pergunta. A conversa fica
                        guardada no Laravel se a resposta da IA for válida.
                    </p>
                </div>

                <p v-if="loadingHistory" style="color: #9e9e9e">
                    A carregar histórico...
                </p>

                <div
                    v-for="message in messages"
                    :key="message.id"
                    :style="{
                        alignSelf:
                            message.role === 'user' ? 'flex-end' : 'flex-start',
                        maxWidth: '82%',
                        display: 'flex',
                        flexDirection: 'column',
                        gap: '4px',
                    }"
                >
                    <div
                        :style="{
                            background:
                                message.role === 'user' ? '#1E1E1E' : '#F5F5F5',
                            color:
                                message.role === 'user' ? '#ffffff' : '#1E1E1E',
                            borderRadius:
                                message.role === 'user'
                                    ? '16px 16px 4px 16px'
                                    : '16px 16px 16px 4px',
                            padding: '14px 18px',
                            lineHeight: 1.65,
                            fontSize: '14px',
                        }"
                    >
                        <MarkdownMessage
                            :text="
                                message.content ||
                                (message.loading ? 'A pensar...' : '')
                            "
                            :tone="message.role"
                            @open-citation="openCitation"
                        />
                    </div>
                </div>

                <div
                    v-if="lastStatus"
                    style="
                        align-self: flex-start;
                        font-size: 12px;
                        color: #9e9e9e;
                    "
                >
                    {{ lastStatus }}
                </div>

                <p v-if="error" style="color: #e53935; font-size: 13px">
                    {{ error }}
                </p>

                <div ref="chatEndRef" />
            </div>
        </div>

        <div
            style="
                position: fixed;
                left: 80px;
                right: 0;
                bottom: 0;
                background: linear-gradient(to top, #ffffff 70%, transparent);
                padding: 18px 24px 22px;
            "
        >
            <div style="max-width: 920px; margin: 0 auto">
                <div
                    style="
                        display: flex;
                        align-items: center;
                        gap: 12px;
                        border: 1px solid #e5e5e5;
                        border-radius: 999px;
                        padding: 8px 8px 8px 18px;
                        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
                        background: #ffffff;
                    "
                >
                    <input
                        v-model="inputValue"
                        type="text"
                        :disabled="isStreaming || !selectedUc"
                        placeholder="Faz qualquer pergunta."
                        style="
                            flex: 1;
                            border: none;
                            outline: none;
                            background: transparent;
                            font-family: Inter, sans-serif;
                            font-size: 14px;
                        "
                        @keydown.enter.prevent="handleSend"
                    />

                    <button
                        :disabled="!canSend"
                        :style="{
                            width: '42px',
                            height: '42px',
                            borderRadius: '50%',
                            border: 'none',
                            background: '#009957',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            cursor: canSend ? 'pointer' : 'not-allowed',
                            opacity: canSend ? 1 : 0.5,
                        }"
                        @click="handleSend"
                    >
                        <Send :size="17" color="#ffffff" />
                    </button>
                </div>
            </div>
        </div>

        <CitationPdfModal
            :open="citationModalOpen"
            :file="citationFile"
            :page="citationPage"
            @close="closeCitation"
        />
    </div>
</template>
