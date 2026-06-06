<script setup lang="ts">
import { computed, ref } from "vue";
import { useRouter } from "vue-router";
import { MoreVertical, Eye, EyeOff, Trash2 } from "@lucide/vue";

defineOptions({ name: "UCCard" });

const props = defineProps<{
    id: string;
    name: string;
    teacher: string;
    year: string;
    academicYear: string;
    cover: string;
    shortCode: string;
    description: string;

    // Props que estavam a ser passadas pela HomePage,
    // mas não estavam declaradas no componente.
    url?: string | null;
    subjectId?: string | number | null;
}>();

const router = useRouter();
const menuOpen = ref(false);

const isGradient = computed(() => {
    return (
        props.cover?.startsWith("linear-gradient") ||
        props.cover?.startsWith("radial-gradient")
    );
});

function navigate() {
    if (props.url) {
        router.push(props.url);
        return;
    }

    router.push({ name: "uc-detail", params: { id: props.id } });
}

function toggleMenu(e: MouseEvent) {
    e.stopPropagation();
    menuOpen.value = !menuOpen.value;
}

function closeMenu() {
    menuOpen.value = false;
}
</script>

<template>
    <div class="uc-card-wrap">
        <!-- Click-away overlay -->
        <div v-if="menuOpen" class="uc-card-click-away" @click="closeMenu" />

        <article class="uc-card" @click="navigate">
            <!-- Cover area -->
            <div class="uc-card-cover">
                <!-- Gradient cover -->
                <div
                    v-if="isGradient"
                    class="uc-card-cover-media"
                    :style="{ background: cover }"
                />

                <!-- Image cover -->
                <img
                    v-else
                    class="uc-card-cover-media"
                    :src="cover"
                    :alt="name"
                />

                <!-- Overlay -->
                <div class="uc-card-cover-overlay" />

                <!-- Short code badge -->
                <div class="uc-card-badge">
                    <span>{{ shortCode }}</span>
                </div>

                <!-- 3-dot menu -->
                <div class="uc-card-menu-wrap">
                    <button
                        type="button"
                        class="uc-card-menu-button"
                        aria-label="Abrir menu da UC"
                        @click="toggleMenu"
                    >
                        <MoreVertical
                            :size="14"
                            :stroke-width="2"
                            color="#ffffff"
                        />
                    </button>

                    <!-- Dropdown menu -->
                    <div v-if="menuOpen" class="uc-card-menu" @click.stop>
                        <button
                            type="button"
                            class="uc-card-menu-item"
                            @click="closeMenu"
                        >
                            <Eye
                                :size="14"
                                :stroke-width="1.8"
                                color="var(--tuts-text-muted)"
                            />

                            Ver UC
                        </button>

                        <button
                            type="button"
                            class="uc-card-menu-item"
                            @click="closeMenu"
                        >
                            <EyeOff
                                :size="14"
                                :stroke-width="1.8"
                                color="var(--tuts-text-muted)"
                            />

                            Ocultar UC
                        </button>

                        <button
                            type="button"
                            class="uc-card-menu-item danger"
                            @click="closeMenu"
                        >
                            <Trash2
                                :size="14"
                                :stroke-width="1.8"
                                color="#E53E3E"
                            />

                            Remover UC
                        </button>
                    </div>
                </div>
            </div>

            <!-- Card body -->
            <div class="uc-card-body">
                <p class="uc-card-title">
                    {{ name }}
                </p>

                <p class="uc-card-teacher">
                    {{ teacher }}
                </p>

                <div class="uc-card-meta">
                    <span>{{ year }}</span>

                    <span class="uc-card-dot">·</span>

                    <span>{{ academicYear }}</span>
                </div>
            </div>
        </article>
    </div>
</template>

<style scoped>
.uc-card-wrap {
    position: relative;
    height: 100%;
}

.uc-card-click-away {
    position: fixed;
    inset: 0;
    z-index: 40;
}

.uc-card {
    height: 100%;
    min-height: 250px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    cursor: pointer;
    border: 1px solid var(--tuts-border-soft);
    border-radius: 16px;
    background: var(--tuts-surface);
    color: var(--tuts-text);
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.04);
    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease,
        transform 0.18s ease,
        background 0.18s ease;
}

.uc-card:hover {
    border-color: color-mix(in srgb, var(--brand, #009957) 42%, var(--tuts-border));
    box-shadow: 0 14px 34px var(--tuts-shadow, rgba(0, 0, 0, 0.08));
    transform: translateY(-1px);
}

.uc-card-cover {
    position: relative;
    height: 120px;
    flex-shrink: 0;
    overflow: hidden;
    background: var(--tuts-surface-soft);
}

.uc-card-cover-media {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.uc-card-cover-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.22);
}

.uc-card-badge {
    position: absolute;
    bottom: 12px;
    left: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    min-height: 28px;
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.34);
    border: 1px solid rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    padding: 4px 10px;
}

.uc-card-badge span {
    font-family: Inter, sans-serif;
    font-size: 13px;
    font-weight: 700;
    color: #ffffff;
}

.uc-card-menu-wrap {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 50;
}

.uc-card-menu-button {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 7px;
    background: rgba(0, 0, 0, 0.34);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    cursor: pointer;
    transition:
        opacity 0.16s ease,
        background 0.16s ease,
        transform 0.16s ease;
}

.uc-card-menu-button:hover {
    opacity: 0.86;
    background: rgba(0, 0, 0, 0.44);
}

.uc-card-menu-button:active {
    transform: scale(0.94);
}

.uc-card-menu {
    position: absolute;
    top: 32px;
    right: 0;
    z-index: 100;
    min-width: 160px;
    overflow: hidden;
    border: 1px solid var(--tuts-border);
    border-radius: 10px;
    background: var(--tuts-elevated, var(--tuts-surface));
    box-shadow: 0 16px 36px var(--tuts-shadow, rgba(0, 0, 0, 0.14));
    padding: 4px;
}

.uc-card-menu-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 8px;
    border: none;
    border-radius: 7px;
    background: transparent;
    color: var(--tuts-text);
    cursor: pointer;
    font-family: Inter, sans-serif;
    font-size: 13px;
    font-weight: 400;
    padding: 10px 12px;
    text-align: left;
    transition:
        background 0.15s ease,
        color 0.15s ease;
}

.uc-card-menu-item:hover {
    background: var(--tuts-surface-soft);
}

.uc-card-menu-item.danger {
    color: #e53e3e;
}

.uc-card-menu-item.danger:hover {
    background: rgba(229, 62, 62, 0.1);
}

.uc-card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 16px;
    background: var(--tuts-surface);
    color: var(--tuts-text);
}

.uc-card-title {
    margin: 0;
    overflow: hidden;
    color: var(--tuts-text);
    display: -webkit-box;
    font-family: Inter, sans-serif;
    font-size: 15px;
    font-weight: 700;
    line-height: 1.3;
    text-overflow: ellipsis;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.uc-card-teacher {
    margin: 0;
    color: var(--tuts-text-soft);
    font-family: Inter, sans-serif;
    font-size: 12px;
    font-weight: 400;
}

.uc-card-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
    color: var(--tuts-text-faint);
    font-family: Inter, sans-serif;
    font-size: 12px;
    font-weight: 400;
}

.uc-card-dot {
    color: var(--tuts-border);
}

:global(html[data-theme="dark"]) .uc-card,
:global(html.dark) .uc-card {
    border-color: rgba(255, 255, 255, 0.075);
    background: var(--tuts-surface);
    box-shadow: none;
}

:global(html[data-theme="dark"]) .uc-card:hover,
:global(html.dark) .uc-card:hover {
    border-color: rgba(0, 153, 87, 0.34);
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.34);
}

:global(html[data-theme="dark"]) .uc-card-body,
:global(html.dark) .uc-card-body {
    background: var(--tuts-surface);
}

:global(html[data-theme="dark"]) .uc-card-cover-overlay,
:global(html.dark) .uc-card-cover-overlay {
    background: rgba(0, 0, 0, 0.18);
}

:global(html[data-theme="dark"]) .uc-card-badge,
:global(html.dark) .uc-card-badge,
:global(html[data-theme="dark"]) .uc-card-menu-button,
:global(html.dark) .uc-card-menu-button {
    background: rgba(9, 12, 10, 0.54);
    border-color: rgba(255, 255, 255, 0.08);
}
</style>
