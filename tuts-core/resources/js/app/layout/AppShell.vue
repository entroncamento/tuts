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
    )
        return "calendar";
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
    <div
        style="
            font-family: Inter, sans-serif;
            background: #ffffff;
            height: 100vh;
            overflow: hidden;
            display: flex;
        "
    >
        <Sidebar :active-page="activePage" />

        <div
            style="
                margin-left: 80px;
                flex: 1;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                height: 100vh;
            "
        >
            <TopNav :breadcrumb="breadcrumb" />

            <div style="flex: 1; margin-top: 72px; overflow: hidden">
                <RouterView />
            </div>
        </div>

        <BottomChatInput v-if="showChatInput" />
    </div>
</template>
