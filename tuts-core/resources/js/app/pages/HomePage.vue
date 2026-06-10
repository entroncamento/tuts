<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { ChevronRight } from "@lucide/vue";
import UCCard from "@/app/components/UCCard.vue";
import WeeklyCalendar from "@/app/components/WeeklyCalendar.vue";
import { fetchMySubjects } from "@/app/services/subjects";
import { UC_LIST, type UCData } from "@/app/data/ucData";
import { useAppRoleStore } from "@/app/stores/appRole";

const router = useRouter();
const roleStore = useAppRoleStore();

const ucs = ref<UCData[]>(UC_LIST);
const loading = ref(false);

const firstName = computed(() => {
    const name = roleStore.user?.name?.trim();

    if (!name) return "Gil";

    return name.split(/\s+/)[0];
});

onMounted(async () => {
    loading.value = true;

    try {
        ucs.value = await fetchMySubjects();
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="home-page">
        <div class="home-page-inner">
            <header class="home-header">
                <h1 class="home-title">Bem-vindo, {{ firstName }}!</h1>

                <p class="home-subtitle">
                    Continua o teu estudo e consulta as unidades curriculares
                    disponíveis.
                </p>

                <span class="home-mode">
                    {{
                        roleStore.role === "teacher"
                            ? "Modo docente"
                            : "Modo estudante"
                    }}
                </span>
            </header>

            <section class="home-section">
                <div class="home-section-header">
                    <h2 class="home-section-title">As tuas UC's</h2>

                    <button
                        type="button"
                        class="home-link-button"
                        @click="router.push({ name: 'ucs' })"
                    >
                        Ver todas

                        <ChevronRight
                            :size="14"
                            :stroke-width="2"
                            color="#009957"
                        />
                    </button>
                </div>

                <p v-if="loading" class="home-state-text">
                    A carregar UCs...
                </p>

                <p v-else-if="ucs.length === 0" class="home-state-text">
                    Ainda não existem UCs associadas à tua conta.
                </p>

                <div v-else class="home-uc-grid">
                    <UCCard
                        v-for="uc in ucs.slice(0, 6)"
                        :key="uc.id"
                        v-bind="uc"
                    />
                </div>
            </section>

            <div class="home-divider" />

            <section class="home-section">
                <div class="home-section-header">
                    <h2 class="home-section-title">O teu calendário</h2>

                    <button
                        type="button"
                        class="home-link-button"
                        @click="router.push({ name: 'calendar' })"
                    >
                        Ver calendário completo

                        <ChevronRight
                            :size="14"
                            :stroke-width="2"
                            color="#009957"
                        />
                    </button>
                </div>

                <div class="home-calendar-wrap">
                    <WeeklyCalendar />
                </div>
            </section>

            <div class="home-bottom-spacer" />
        </div>
    </div>
</template>

<style scoped>
.home-page {
    height: 100%;
    overflow-y: auto;
    padding-bottom: 110px;
    background: var(--tuts-bg);
    color: var(--tuts-text);
}

.home-page-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 24px;
}

.home-header {
    margin-bottom: 40px;
}

.home-title {
    margin: 0 0 8px;
    font-family: Inter, sans-serif;
    font-size: 32px;
    font-weight: 700;
    line-height: 1.2;
    color: var(--tuts-text);
}

.home-subtitle {
    margin: 0 0 4px;
    font-family: Inter, sans-serif;
    font-size: 15px;
    font-weight: 400;
    color: var(--tuts-text-faint);
}

.home-mode {
    font-family: Inter, sans-serif;
    font-size: 12px;
    font-weight: 400;
    color: var(--tuts-text-faint);
}

.home-section {
    margin-bottom: 40px;
}

.home-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
}

.home-section-title {
    margin: 0;
    font-family: Inter, sans-serif;
    font-size: 20px;
    font-weight: 700;
    color: var(--tuts-text);
}

.home-link-button {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 0;
    border: none;
    background: none;
    cursor: pointer;
    font-family: Inter, sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #009957;
    transition: opacity 0.18s ease;
}

.home-link-button:hover {
    opacity: 0.72;
}

.home-state-text {
    margin: 0;
    font-family: Inter, sans-serif;
    font-size: 14px;
    color: var(--tuts-text-soft);
}

.home-uc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    align-items: stretch;
    gap: 20px;
}

.home-divider {
    height: 1px;
    margin-bottom: 40px;
    background: var(--tuts-border-soft);
}

.home-calendar-wrap {
    color: var(--tuts-text);
}

.home-bottom-spacer {
    height: 32px;
}

:global(html[data-theme="dark"]) .home-page,
:global(html.dark) .home-page {
    background: var(--tuts-bg) !important;
    color: var(--tuts-text) !important;
}

:global(html[data-theme="dark"]) .home-calendar-wrap,
:global(html.dark) .home-calendar-wrap {
    color: var(--tuts-text) !important;
}

:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="background: #ffffff"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="background:#ffffff"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="background: #FFFFFF"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="background:#FFFFFF"]),
:global(html.dark) .home-calendar-wrap :deep([style*="background: #ffffff"]),
:global(html.dark) .home-calendar-wrap :deep([style*="background:#ffffff"]),
:global(html.dark) .home-calendar-wrap :deep([style*="background: #FFFFFF"]),
:global(html.dark) .home-calendar-wrap :deep([style*="background:#FFFFFF"]) {
    background: var(--tuts-surface) !important;
}

:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="background: #F7F7F7"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="background:#F7F7F7"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="background: #f7f7f7"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="background:#f7f7f7"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="background: #F5F5F5"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="background:#F5F5F5"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="background: #f5f5f5"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="background:#f5f5f5"]),
:global(html.dark) .home-calendar-wrap :deep([style*="background: #F7F7F7"]),
:global(html.dark) .home-calendar-wrap :deep([style*="background:#F7F7F7"]),
:global(html.dark) .home-calendar-wrap :deep([style*="background: #f7f7f7"]),
:global(html.dark) .home-calendar-wrap :deep([style*="background:#f7f7f7"]),
:global(html.dark) .home-calendar-wrap :deep([style*="background: #F5F5F5"]),
:global(html.dark) .home-calendar-wrap :deep([style*="background:#F5F5F5"]),
:global(html.dark) .home-calendar-wrap :deep([style*="background: #f5f5f5"]),
:global(html.dark) .home-calendar-wrap :deep([style*="background:#f5f5f5"]) {
    background: var(--tuts-surface-soft) !important;
}

:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="color: #1E1E1E"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="color:#1E1E1E"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="color: #1e1e1e"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="color:#1e1e1e"]),
:global(html.dark) .home-calendar-wrap :deep([style*="color: #1E1E1E"]),
:global(html.dark) .home-calendar-wrap :deep([style*="color:#1E1E1E"]),
:global(html.dark) .home-calendar-wrap :deep([style*="color: #1e1e1e"]),
:global(html.dark) .home-calendar-wrap :deep([style*="color:#1e1e1e"]) {
    color: var(--tuts-text) !important;
}

:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="color: #656966"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="color:#656966"]),
:global(html.dark) .home-calendar-wrap :deep([style*="color: #656966"]),
:global(html.dark) .home-calendar-wrap :deep([style*="color:#656966"]) {
    color: var(--tuts-text-muted) !important;
}

:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="#E5E5E5"]),
:global(html[data-theme="dark"]) .home-calendar-wrap :deep([style*="#e5e5e5"]),
:global(html.dark) .home-calendar-wrap :deep([style*="#E5E5E5"]),
:global(html.dark) .home-calendar-wrap :deep([style*="#e5e5e5"]) {
    border-color: var(--tuts-border) !important;
}
</style>
