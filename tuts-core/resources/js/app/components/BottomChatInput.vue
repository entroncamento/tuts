<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Plus, Send, FileText, Zap, BookOpen, X } from "@lucide/vue";
import { useShellStore } from "@/app/stores/shell";
import ImportMaterialModal from "./ImportMaterialModal.vue";
import type { PersonalFile } from "./ImportMaterialModal.vue";

defineOptions({ name: "BottomChatInput" });

function extractSegmentAfter(
    pathname: string,
    prefix: string,
): string | undefined {
    const parts = pathname.split("/").filter(Boolean);
    const idx = parts.indexOf(prefix);

    if (idx === -1 || idx + 1 >= parts.length) return undefined;

    return parts[idx + 1];
}

const COMMANDS = [
    { id: "c1", cmd: "/quiz", desc: "Gerar um teste rápido" },
    { id: "c2", cmd: "/resumo", desc: "Sintetizar matéria" },
    { id: "c3", cmd: "/grafico", desc: "Criar mapa mental" },
];

const MENTIONS = [
    { id: "m1", name: "Redes de Computadores" },
    { id: "m2", name: "Sistemas Operativos" },
    { id: "m3", name: "Matemática Discreta" },
    { id: "m4", name: "Exames 2026" },
];

const route = useRoute();
const router = useRouter();
const shellStore = useShellStore();

const text = ref("");
const isFileModalOpen = ref(false);
const attachedFiles = ref<PersonalFile[]>([]);
const activePopup = ref<"mention" | "command" | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);

const hasContent = computed(() => text.value.trim().length > 0);
const canSend = computed(() => hasContent.value || attachedFiles.value.length > 0);

onMounted(() => {
    shellStore.registerFocusHandler(() => inputRef.value?.focus());
});

onUnmounted(() => {
    shellStore.unregisterFocusHandler();
});

function handleMentionInsert(e: Event): void {
    const customEvent = e as CustomEvent<string>;

    text.value += customEvent.detail;
    inputRef.value?.focus();
}

onMounted(() => {
    window.addEventListener("insertChatMention", handleMentionInsert);
});

onUnmounted(() => {
    window.removeEventListener("insertChatMention", handleMentionInsert);
});

function handleOutsideClick(e: MouseEvent): void {
    const bar = document.getElementById("tuts-chat-bar");

    if (bar && !bar.contains(e.target as Node)) {
        activePopup.value = null;
    }
}

onMounted(() => {
    document.addEventListener("mousedown", handleOutsideClick, true);
});

onUnmounted(() => {
    document.removeEventListener("mousedown", handleOutsideClick, true);
});

function handleTextInput(e: Event): void {
    const val = (e.target as HTMLInputElement).value;

    text.value = val;

    if (val.endsWith("/")) {
        activePopup.value = "command";
    } else if (val.endsWith("@")) {
        activePopup.value = "mention";
    } else if (!val.includes("/") && !val.includes("@")) {
        activePopup.value = null;
    }

    if (val.trim() === "") {
        activePopup.value = null;
    }
}

function handleSelectSuggestion(suggestion: string): void {
    text.value = text.value.slice(0, -1) + suggestion + " ";
    activePopup.value = null;
    inputRef.value?.focus();
}

function handleKeydown(e: KeyboardEvent): void {
    if (e.key === "Escape") {
        activePopup.value = null;
        return;
    }

    if (e.key === "Enter" && !e.shiftKey) {
        if (activePopup.value) return;

        e.preventDefault();
        handleSend();
    }
}

function handleSend(): void {
    const pathname = route.path;
    const trimmed = text.value.trim();

    if (
        trimmed === "" &&
        attachedFiles.value.length === 0 &&
        pathname !== "/calendar"
    ) {
        return;
    }

    if (pathname === "/calendar") {
        router.push({ name: "planning" });
        text.value = "";
        return;
    }

    let sourceContext = "global";
    let sourceId: string | null = null;

    if (pathname.startsWith("/uc/")) {
        sourceContext = "uc";
        sourceId = extractSegmentAfter(pathname, "uc") ?? null;
    } else if (pathname.startsWith("/space/")) {
        sourceContext = "space";
        sourceId = extractSegmentAfter(pathname, "space") ?? null;
    }

    router.push({
        name: "chat",
        state: { initialMessage: trimmed, sourceContext, sourceId },
    });

    text.value = "";
    activePopup.value = null;
}

function handleFilesConfirmed(files: PersonalFile[]): void {
    const existingIds = new Set(attachedFiles.value.map((f) => f.id));

    attachedFiles.value = [
        ...attachedFiles.value,
        ...files.filter((f) => !existingIds.has(f.id)),
    ];

    isFileModalOpen.value = false;
}

function removeAttachedFile(id: string): void {
    attachedFiles.value = attachedFiles.value.filter((f) => f.id !== id);
}
</script>

<template>
    <div id="tuts-chat-bar" class="bottom-chat">
        <div class="bottom-chat-popover-anchor">
            <div v-if="activePopup" class="bottom-chat-popover">
                <button
                    v-if="activePopup === 'command'"
                    v-for="c in COMMANDS"
                    :key="c.id"
                    type="button"
                    class="bottom-chat-suggestion"
                    @click="handleSelectSuggestion(c.cmd)"
                >
                    <div class="bottom-chat-suggestion-icon command">
                        <Zap :size="14" color="#F57C00" />
                    </div>

                    <div class="bottom-chat-suggestion-text">
                        <p class="bottom-chat-suggestion-title">
                            {{ c.cmd }}
                        </p>

                        <p class="bottom-chat-suggestion-description">
                            {{ c.desc }}
                        </p>
                    </div>
                </button>

                <button
                    v-if="activePopup === 'mention'"
                    v-for="m in MENTIONS"
                    :key="m.id"
                    type="button"
                    class="bottom-chat-suggestion"
                    @click="handleSelectSuggestion(`@${m.name}`)"
                >
                    <div class="bottom-chat-suggestion-icon mention">
                        <BookOpen :size="14" color="#009957" />
                    </div>

                    <p class="bottom-chat-suggestion-title">
                        {{ m.name }}
                    </p>
                </button>
            </div>
        </div>

        <div
            class="bottom-chat-surface"
            :class="{ 'has-files': attachedFiles.length > 0 }"
        >
            <div v-if="attachedFiles.length > 0" class="attached-files">
                <div
                    v-for="f in attachedFiles"
                    :key="f.id"
                    class="attached-file-chip"
                >
                    <FileText
                        :size="12"
                        color="#009957"
                        class="attached-file-icon"
                    />

                    <span class="attached-file-name">
                        {{ f.name }}
                    </span>

                    <button
                        type="button"
                        class="attached-file-remove"
                        :aria-label="`Remover ${f.name}`"
                        @click="removeAttachedFile(f.id)"
                    >
                        <X :size="12" color="var(--tuts-text-soft)" />
                    </button>
                </div>
            </div>

            <div class="bottom-chat-row">
                <button
                    type="button"
                    aria-label="Adicionar ficheiro"
                    class="bottom-chat-add-button"
                    @click="isFileModalOpen = true"
                >
                    <Plus
                        :size="16"
                        :stroke-width="2"
                        color="var(--tuts-text)"
                    />
                </button>

                <input
                    ref="inputRef"
                    type="text"
                    class="bottom-chat-input"
                    :value="text"
                    placeholder="Faz qualquer pergunta..."
                    @input="handleTextInput"
                    @keydown="handleKeydown"
                />

                <button
                    type="button"
                    aria-label="Enviar mensagem"
                    class="bottom-chat-send-button"
                    :class="{ active: canSend }"
                    @click="handleSend"
                >
                    <Send :size="16" :stroke-width="2" color="#ffffff" />
                </button>
            </div>
        </div>

        <div class="bottom-chat-footer">
            <span>
                Criado por alunos e docentes que valorizam a qualidade, e com
                princípios de
                <strong>Responsible AI</strong>
            </span>
        </div>

        <ImportMaterialModal
            v-if="isFileModalOpen"
            @confirm="handleFilesConfirmed"
            @close="isFileModalOpen = false"
        />
    </div>
</template>

<style scoped>
.bottom-chat {
    position: fixed;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 50;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 64px 20px 20px 100px;
    background: linear-gradient(
        to top,
        var(--tuts-bg) 0%,
        color-mix(in srgb, var(--tuts-bg) 92%, transparent) 58%,
        transparent 100%
    );
    pointer-events: none;
    transition:
        background 0.25s ease,
        color 0.25s ease;
}

.bottom-chat-popover-anchor,
.bottom-chat-surface,
.bottom-chat-footer {
    pointer-events: auto;
}

.bottom-chat-popover-anchor {
    position: relative;
    width: 100%;
    max-width: 860px;
}

.bottom-chat-popover {
    position: absolute;
    bottom: 100%;
    left: 40px;
    z-index: 60;
    display: flex;
    width: min(320px, calc(100vw - 140px));
    max-height: 220px;
    min-width: 240px;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 12px;
    overflow-y: auto;
    border: 1px solid var(--tuts-border);
    border-radius: 16px;
    background: var(--tuts-elevated, var(--tuts-surface));
    box-shadow: 0 18px 45px var(--tuts-shadow, rgba(0, 0, 0, 0.12));
    padding: 8px;
}

.bottom-chat-suggestion {
    display: flex;
    width: 100%;
    cursor: pointer;
    align-items: center;
    gap: 10px;
    border: none;
    border-radius: 10px;
    background: transparent;
    padding: 10px 14px;
    text-align: left;
    transition: background 0.15s ease;
}

.bottom-chat-suggestion:hover {
    background: var(--tuts-surface-soft);
}

.bottom-chat-suggestion-icon {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    padding: 6px;
}

.bottom-chat-suggestion-icon.command {
    background: rgba(245, 124, 0, 0.12);
}

.bottom-chat-suggestion-icon.mention {
    background: rgba(0, 153, 87, 0.12);
}

.bottom-chat-suggestion-text {
    min-width: 0;
}

.bottom-chat-suggestion-title {
    margin: 0;
    color: var(--tuts-text);
    font-family: Inter, sans-serif;
    font-size: 13px;
    font-weight: 600;
}

.bottom-chat-suggestion-description {
    margin: 0;
    color: var(--tuts-text-soft);
    font-family: Inter, sans-serif;
    font-size: 11px;
    font-weight: 400;
}

.bottom-chat-surface {
    display: flex;
    width: 100%;
    max-width: 860px;
    flex-direction: column;
    gap: 0;
    border: 1px solid var(--tuts-border);
    border-radius: 40px;
    background: var(--tuts-chat-bg, var(--tuts-surface));
    box-shadow: 0 18px 42px var(--tuts-shadow, rgba(0, 0, 0, 0.12));
    padding: 10px 10px 10px 16px;
    transition:
        border-radius 0.2s ease,
        background 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.bottom-chat-surface.has-files {
    border-radius: 20px;
    padding: 12px 12px 10px 16px;
}

.attached-files {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
    border-bottom: 1px solid var(--tuts-border-soft);
    padding-bottom: 10px;
}

.attached-file-chip {
    display: flex;
    max-width: 200px;
    align-items: center;
    gap: 6px;
    border: 1px solid var(--tuts-border);
    border-radius: 8px;
    background: var(--tuts-surface-soft);
    padding: 4px 10px;
}

.attached-file-icon {
    flex-shrink: 0;
}

.attached-file-name {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    color: var(--tuts-text);
    font-size: 11px;
    font-weight: 500;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.attached-file-remove {
    display: flex;
    flex-shrink: 0;
    cursor: pointer;
    border: none;
    background: transparent;
    padding: 2px;
}

.bottom-chat-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.bottom-chat-add-button,
.bottom-chat-send-button {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 999px;
    cursor: pointer;
    outline: none;
    transition:
        background 0.18s ease,
        opacity 0.18s ease,
        transform 0.18s ease;
}

.bottom-chat-add-button {
    width: 36px;
    height: 36px;
    background: var(--tuts-surface-soft);
}

.bottom-chat-add-button:hover {
    background: var(--tuts-surface-muted);
}

.bottom-chat-input {
    flex: 1;
    min-width: 0;
    border: none;
    outline: none;
    background: transparent;
    color: var(--tuts-text);
    font-family: Inter, sans-serif;
    font-size: 14px;
    font-weight: 400;
}

.bottom-chat-input::placeholder {
    color: var(--tuts-text-soft);
}

.bottom-chat-send-button {
    width: 40px;
    height: 40px;
    background: var(--tuts-text);
}

.bottom-chat-send-button.active {
    background: #009957;
}

.bottom-chat-send-button:hover {
    opacity: 0.84;
}

.bottom-chat-send-button:active,
.bottom-chat-add-button:active {
    transform: scale(0.96);
}

.bottom-chat-footer {
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--tuts-text-faint);
    font-family: Inter, sans-serif;
    font-size: 10px;
    font-weight: 400;
    text-align: center;
}

.bottom-chat-footer strong {
    font-weight: 700;
    text-decoration: underline;
}

:global(html[data-theme="dark"]) .bottom-chat,
:global(html.dark) .bottom-chat {
    background: linear-gradient(
        to top,
        var(--tuts-bg) 0%,
        color-mix(in srgb, var(--tuts-bg) 90%, transparent) 54%,
        transparent 100%
    ) !important;
}

:global(html[data-theme="dark"]) .bottom-chat-surface,
:global(html.dark) .bottom-chat-surface {
    background: var(--tuts-chat-bg, var(--tuts-surface)) !important;
    border-color: var(--tuts-border) !important;
    box-shadow: 0 18px 44px rgba(0, 0, 0, 0.48) !important;
}

@media (max-width: 900px) {
    .bottom-chat {
        padding-left: 92px;
        padding-right: 16px;
    }

    .bottom-chat-popover {
        left: 0;
    }
}
</style>
