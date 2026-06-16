<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{
    text: string;
    tone?: "user" | "assistant" | "incoming" | "outgoing";
}>();

const emit = defineEmits<{
    (e: "open-citation", payload: { file: string; page: number }): void;
}>();

function escapeHtml(value: string): string {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function renderCitation(file: string, page: string): string {
    const safeFile = escapeHtml(file);
    const safePageNumber = Number(page);
    const safePage =
        Number.isFinite(safePageNumber) && safePageNumber > 0
            ? Math.floor(safePageNumber)
            : 1;

    return `
        <button
            type="button"
            class="citation-button"
            data-citation-file="${safeFile}"
            data-citation-page="${safePage}"
            title="Abrir fonte na página ${safePage}"
        >
            [${safeFile}:${safePage}]
        </button>
    `;
}

function renderInline(value: string): string {
    let html = escapeHtml(value);

    html = html.replace(/`([^`]+)`/g, "<code>$1</code>");
    html = html.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
    html = html.replace(/__([^_]+)__/g, "<strong>$1</strong>");
    html = html.replace(/\*([^*]+)\*/g, "<em>$1</em>");

    html = html.replace(/\[([^\]\n]+?\.pdf):(\d+)\]/gi, (_match, file, page) =>
        renderCitation(file, page),
    );

    return html;
}

function flushParagraph(buffer: string[], output: string[]) {
    if (buffer.length === 0) return;

    output.push(`<p>${buffer.map(renderInline).join("<br>")}</p>`);
    buffer.length = 0;
}

function renderMarkdown(value: string): string {
    const text = String(value ?? "")
        .replace(/\r\n/g, "\n")
        .trim();

    if (!text) return "";

    const lines = text.split("\n");
    const output: string[] = [];
    const paragraph: string[] = [];

    let listType: "ul" | "ol" | null = null;
    let listItems: string[] = [];

    function flushList() {
        if (!listType || listItems.length === 0) return;

        output.push(
            `<${listType}>${listItems
                .map((item) => `<li>${renderInline(item)}</li>`)
                .join("")}</${listType}>`,
        );

        listType = null;
        listItems = [];
    }

    for (const rawLine of lines) {
        const line = rawLine.trim();

        if (!line) {
            flushParagraph(paragraph, output);
            flushList();
            continue;
        }

        const headingMatch = line.match(/^(#{1,6})\s+(.+)$/);

        if (headingMatch) {
            flushParagraph(paragraph, output);
            flushList();

            const level = Math.min(headingMatch[1].length, 4);
            const content = headingMatch[2];

            output.push(`<h${level}>${renderInline(content)}</h${level}>`);
            continue;
        }

        const unorderedMatch = line.match(/^[-*]\s+(.+)$/);

        if (unorderedMatch) {
            flushParagraph(paragraph, output);

            if (listType && listType !== "ul") flushList();

            listType = "ul";
            listItems.push(unorderedMatch[1]);
            continue;
        }

        const orderedMatch = line.match(/^\d+\.\s+(.+)$/);

        if (orderedMatch) {
            flushParagraph(paragraph, output);

            if (listType && listType !== "ol") flushList();

            listType = "ol";
            listItems.push(orderedMatch[1]);
            continue;
        }

        flushList();
        paragraph.push(line);
    }

    flushParagraph(paragraph, output);
    flushList();

    return output.join("");
}

function handleClick(event: MouseEvent) {
    const target = event.target as HTMLElement | null;

    const citationButton = target?.closest<HTMLButtonElement>(
        "button[data-citation-file][data-citation-page]",
    );

    if (!citationButton) return;

    const file = citationButton.dataset.citationFile ?? "";
    const pageRaw = citationButton.dataset.citationPage ?? "1";
    const page = Number(pageRaw);

    if (!file) return;

    emit("open-citation", {
        file,
        page: Number.isFinite(page) && page > 0 ? Math.floor(page) : 1,
    });
}

const rendered = computed(() => renderMarkdown(props.text));

const isOutgoing = computed(
    () => props.tone === "user" || props.tone === "outgoing",
);
</script>

<template>
    <div
        class="markdown-message"
        :class="{ outgoing: isOutgoing }"
        @click="handleClick"
        v-html="rendered"
    />
</template>

<style scoped>
.markdown-message {
    font-family: Inter, sans-serif;
    font-weight: 400;
    font-size: 14px;
    color: #1e1e1e;
    line-height: 1.65;
    overflow-wrap: anywhere;
}

.markdown-message.outgoing {
    color: #ffffff;
}

.markdown-message :deep(h1),
.markdown-message :deep(h2),
.markdown-message :deep(h3),
.markdown-message :deep(h4) {
    font-family: Inter, sans-serif;
    font-weight: 800;
    color: inherit;
    line-height: 1.3;
    margin: 0 0 10px;
}

.markdown-message :deep(h1) {
    font-size: 20px;
}

.markdown-message :deep(h2) {
    font-size: 18px;
}

.markdown-message :deep(h3) {
    font-size: 16px;
}

.markdown-message :deep(h4) {
    font-size: 15px;
}

.markdown-message :deep(p) {
    margin: 0 0 10px;
}

.markdown-message :deep(p:last-child),
.markdown-message :deep(ul:last-child),
.markdown-message :deep(ol:last-child),
.markdown-message :deep(h1:last-child),
.markdown-message :deep(h2:last-child),
.markdown-message :deep(h3:last-child),
.markdown-message :deep(h4:last-child) {
    margin-bottom: 0;
}

.markdown-message :deep(strong) {
    font-weight: 800;
    color: inherit;
}

.markdown-message :deep(em) {
    font-style: italic;
}

.markdown-message :deep(ul),
.markdown-message :deep(ol) {
    margin: 0 0 10px 18px;
    padding: 0;
}

.markdown-message :deep(li) {
    margin: 4px 0;
    padding-left: 2px;
}

.markdown-message :deep(code) {
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    background: rgba(0, 0, 0, 0.06);
    border-radius: 5px;
    padding: 1px 5px;
}

.markdown-message.outgoing :deep(code) {
    background: rgba(255, 255, 255, 0.18);
}

.markdown-message :deep(.citation-button) {
    appearance: none;
    border: 1px solid rgba(0, 153, 87, 0.24);
    background: rgba(0, 153, 87, 0.08);
    color: #007d49;
    border-radius: 999px;
    padding: 2px 7px;
    margin: 0 2px;
    font-family: Inter, sans-serif;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.4;
    cursor: pointer;
    vertical-align: baseline;
}

.markdown-message :deep(.citation-button:hover) {
    background: rgba(0, 153, 87, 0.14);
    border-color: rgba(0, 153, 87, 0.38);
}

.markdown-message.outgoing :deep(.citation-button) {
    background: rgba(255, 255, 255, 0.16);
    border-color: rgba(255, 255, 255, 0.28);
    color: #ffffff;
}
</style>
