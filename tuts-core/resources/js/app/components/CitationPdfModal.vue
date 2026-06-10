<script setup lang="ts">
import { computed } from "vue";
import { ExternalLink, X } from "@lucide/vue";

const props = defineProps<{
    open: boolean;
    file: string;
    page: number;
}>();

const emit = defineEmits<{
    (e: "close"): void;
}>();

const safePage = computed(() => {
    const page = Number(props.page);

    if (!Number.isFinite(page) || page < 1) {
        return 1;
    }

    return Math.floor(page);
});

const pdfUrl = computed(() => {
    if (!props.file) return "";

    return `/pdfs/${encodeURIComponent(props.file)}#page=${safePage.value}`;
});

const title = computed(() => {
    if (!props.file) return "Fonte";

    return `${props.file} · pág. ${safePage.value}`;
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            style="
                position: fixed;
                inset: 0;
                z-index: 9999;
                background: rgba(0, 0, 0, 0.58);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 28px;
                box-sizing: border-box;
            "
            @click.self="emit('close')"
        >
            <section
                style="
                    width: min(1180px, 100%);
                    height: min(820px, 92vh);
                    background: #ffffff;
                    border-radius: 18px;
                    overflow: hidden;
                    box-shadow: 0 30px 90px rgba(0, 0, 0, 0.35);
                    display: flex;
                    flex-direction: column;
                "
            >
                <header
                    style="
                        height: 58px;
                        min-height: 58px;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 16px;
                        padding: 0 16px 0 20px;
                        border-bottom: 1px solid #e5e5e5;
                        box-sizing: border-box;
                    "
                >
                    <div style="min-width: 0">
                        <p
                            style="
                                margin: 0;
                                font-family: Inter, sans-serif;
                                font-size: 13px;
                                font-weight: 750;
                                color: #1e1e1e;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                white-space: nowrap;
                            "
                        >
                            {{ title }}
                        </p>

                        <p
                            style="
                                margin: 2px 0 0;
                                font-family: Inter, sans-serif;
                                font-size: 11px;
                                color: #9e9e9e;
                            "
                        >
                            Fonte citada nos materiais da UC
                        </p>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px">
                        <a
                            v-if="pdfUrl"
                            :href="pdfUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            style="
                                width: 36px;
                                height: 36px;
                                border-radius: 10px;
                                border: 1px solid #e5e5e5;
                                background: #ffffff;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                color: #656966;
                                text-decoration: none;
                            "
                            title="Abrir noutra aba"
                        >
                            <ExternalLink :size="16" />
                        </a>

                        <button
                            type="button"
                            style="
                                width: 36px;
                                height: 36px;
                                border-radius: 10px;
                                border: 1px solid #e5e5e5;
                                background: #ffffff;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                cursor: pointer;
                                color: #656966;
                            "
                            title="Fechar"
                            @click="emit('close')"
                        >
                            <X :size="17" />
                        </button>
                    </div>
                </header>

                <div style="flex: 1; background: #f5f5f5">
                    <iframe
                        v-if="pdfUrl"
                        :src="pdfUrl"
                        title="PDF citado"
                        style="
                            width: 100%;
                            height: 100%;
                            border: 0;
                            display: block;
                            background: #f5f5f5;
                        "
                    />
                </div>
            </section>
        </div>
    </Teleport>
</template>
