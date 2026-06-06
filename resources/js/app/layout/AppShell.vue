<script setup lang="ts">
import { computed } from "vue";
import { useRoute, RouterView } from "vue-router";
import Sidebar from "@/app/components/Sidebar.vue";
import TopNav from "@/app/components/TopNav.vue";
import BottomChatInput from "@/app/components/BottomChatInput.vue";
import { useAppRoleStore } from "@/app/stores/appRole";
import { UC_MAP } from "@/app/data/ucData";
import type { ActivePageId } from "@/app/types";

const route = useRoute();
const roleStore = useAppRoleStore();

const activePage = computed<ActivePageId>(() => {
    const p = route.path;

    if (p.startsWith("/uc/")) return "home";
    if (p.startsWith("/space/")) return "ucs";

    if (
        p === "/calendar" ||
        p.startsWith("/planificacao") ||
        p === "/meus-planos"
    ) {
        return "calendar";
    }

    if (p === "/ucs" || p === "/spaces") return "ucs";
    if (p === "/chat") return "chat";
    if (p === "/profile") return "profile";
    if (p === "/dashboard") return "dashboard";

    return "home";
});

const breadcrumb = computed<string>(() => {
    const p = route.path;

    if (p.startsWith("/uc/")) {
        const id = p.replace("/uc/", "");
        const uc = UC_MAP[id];

        return uc ? `Homepage > ${uc.name}` : "Homepage > UC";
    }

    if (p.startsWith("/space/")) return "UC's e Espaços > Espaço";
    if (p.startsWith("/planificacao/")) return "Planificação";

    switch (p) {
        case "/home":
            return "Homepage";
        case "/chat":
            return "Chat Hub";
        case "/ucs":
            return "UC's e Espaços";
        case "/spaces":
            return "Espaços";
        case "/calendar":
            return "Calendário";
        case "/planificacao":
            return "Planificação";
        case "/meus-planos":
            return "Os Meus Planos";
        case "/profile":
            return "Perfil";
        case "/dashboard":
            return "Dashboard Pedagógico";
        default:
            return "Homepage";
    }
});

const showChatInput = computed<boolean>(() => {
    const p = route.path;
    const ap = activePage.value;

    return (
        p !== "/chat" &&
        p !== "/ucs" &&
        p !== "/spaces" &&
        ap !== "dashboard" &&
        p !== "/profile" &&
        !p.startsWith("/planificacao") &&
        p !== "/meus-planos" &&
        !p.startsWith("/space/") &&
        !(p === "/ucs" && roleStore.role === "teacher")
    );
});
</script>

<template>
    <div class="app-shell">
        <Sidebar :active-page="activePage" />

        <main class="app-shell-main">
            <TopNav :breadcrumb="breadcrumb" />

            <div class="app-shell-content">
                <RouterView />
            </div>
        </main>

        <BottomChatInput v-if="showChatInput" />
    </div>
</template>

<style scoped>
.app-shell {
    width: 100%;
    height: 100vh;
    overflow: hidden;
    display: flex;
    font-family: Inter, sans-serif;
    background: var(--tuts-bg);
    color: var(--tuts-text);
}

.app-shell-main {
    margin-left: 80px;
    flex: 1;
    min-width: 0;
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--tuts-bg);
    color: var(--tuts-text);
}

.app-shell-content {
    flex: 1;
    min-height: 0;
    margin-top: 72px;
    overflow: hidden;
    background: var(--tuts-bg);
    color: var(--tuts-text);
}

:global(html[data-theme="dark"]) .app-shell,
:global(html.dark) .app-shell,
:global(html[data-theme="dark"]) .app-shell-main,
:global(html.dark) .app-shell-main,
:global(html[data-theme="dark"]) .app-shell-content,
:global(html.dark) .app-shell-content {
    background: var(--tuts-bg) !important;
    color: var(--tuts-text) !important;
}
</style>
