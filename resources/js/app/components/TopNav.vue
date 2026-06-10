<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { Bell, Search, Info } from "@lucide/vue";
import { useAppRoleStore } from "@/app/stores/appRole";

defineOptions({ name: "AppTopNav" });

withDefaults(
    defineProps<{
        breadcrumb?: string;
    }>(),
    {
        breadcrumb: "Homepage",
    },
);

interface TutsNotification {
    id: number;
    type: string;
    title: string;
    body: string | null;
    data: Record<string, unknown>;
    scheduled_for: string | null;
    read_at: string | null;
    created_at: string | null;
    is_read: boolean;
}

const router = useRouter();
const roleStore = useAppRoleStore();

const notificationsOpen = ref(false);
const notifications = ref<TutsNotification[]>([]);
const unreadCount = ref(0);
const loadingNotifications = ref(false);

const avatar = computed(() => {
    const name = roleStore.user?.name ?? "Utilizador";
    const parts = name.trim().split(/\s+/);
    const initials =
        parts.length >= 2
            ? `${parts[0][0]}${parts[parts.length - 1][0]}`
            : name.slice(0, 2);

    return {
        initials: initials.toUpperCase(),
        name: name.toUpperCase(),
        subtitle: roleStore.role === "teacher" ? "Docente" : "Estudante",
        avatarBg:
            roleStore.role === "teacher"
                ? "var(--color-info)"
                : "var(--color-primary)",
        fontSize: initials.length > 1 ? 13 : 16,
    };
});

function getCsrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute("content") ?? ""
    );
}

function formatDate(date: string | null): string {
    if (!date) return "";

    try {
        return new Intl.DateTimeFormat("pt-PT", {
            day: "2-digit",
            month: "short",
            hour: "2-digit",
            minute: "2-digit",
        }).format(new Date(date));
    } catch {
        return "";
    }
}

async function loadNotifications(): Promise<void> {
    loadingNotifications.value = true;

    try {
        const response = await fetch("/api/notifications?limit=8", {
            headers: {
                Accept: "application/json",
            },
            credentials: "same-origin",
        });

        if (!response.ok) {
            throw new Error(
                `Erro ao carregar notificações: ${response.status}`,
            );
        }

        const data = await response.json();

        notifications.value = data.notifications ?? [];
        unreadCount.value = data.unread_count ?? 0;
    } catch (error) {
        console.error("[TUTS] Falha ao carregar notificações.", error);
    } finally {
        loadingNotifications.value = false;
    }
}

async function toggleNotifications(): Promise<void> {
    notificationsOpen.value = !notificationsOpen.value;

    if (notificationsOpen.value) {
        await loadNotifications();
    }
}

function closeNotifications(): void {
    notificationsOpen.value = false;
}

async function markNotificationAsRead(
    notification: TutsNotification,
): Promise<void> {
    if (notification.is_read) return;

    try {
        const response = await fetch(
            `/api/notifications/${notification.id}/read`,
            {
                method: "PATCH",
                headers: {
                    Accept: "application/json",
                    "X-CSRF-TOKEN": getCsrfToken(),
                },
                credentials: "same-origin",
            },
        );

        if (!response.ok) {
            throw new Error(
                `Erro ao marcar notificação como lida: ${response.status}`,
            );
        }

        notification.is_read = true;
        notification.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    } catch (error) {
        console.error("[TUTS] Falha ao marcar notificação como lida.", error);
    }
}

async function markAllNotificationsAsRead(): Promise<void> {
    try {
        const response = await fetch("/api/notifications/read-all", {
            method: "PATCH",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": getCsrfToken(),
            },
            credentials: "same-origin",
        });

        if (!response.ok) {
            throw new Error(
                `Erro ao marcar todas como lidas: ${response.status}`,
            );
        }

        notifications.value = notifications.value.map((notification) => ({
            ...notification,
            is_read: true,
            read_at: new Date().toISOString(),
        }));

        unreadCount.value = 0;
    } catch (error) {
        console.error("[TUTS] Falha ao marcar todas como lidas.", error);
    }
}

async function handleNotificationClick(
    notification: TutsNotification,
): Promise<void> {
    await markNotificationAsRead(notification);

    const url = notification.data?.url;

    if (typeof url === "string" && url.trim().length > 0) {
        closeNotifications();
        await router.push(url);
    }
}

onMounted(() => {
    loadNotifications();
});
</script>

<template>
    <header
        class="fixed top-0 right-0 z-20 flex items-center"
        style="
            left: 80px;
            height: 72px;
            background: var(--color-surface);
            border-bottom: 1px solid var(--color-border-soft);
            padding-left: 24px;
            padding-right: 24px;
        "
    >
        <div class="flex items-center gap-2 flex-1">
            <span
                style="
                    font-family: Inter, sans-serif;
                    font-weight: 500;
                    font-size: 13px;
                    color: var(--color-text-soft);
                "
            >
                {{ breadcrumb }}
            </span>

            <span
                v-if="roleStore.role === 'teacher'"
                style="
                    font-family: Inter, sans-serif;
                    font-weight: 600;
                    font-size: 10px;
                    color: var(--color-info);
                    background: var(--color-info-soft);
                    border-radius: 4px;
                    padding: 2px 7px;
                    letter-spacing: 0.04em;
                    white-space: nowrap;
                "
            >
                MODO DOCENTE
            </span>
        </div>

        <div class="absolute left-1/2 -translate-x-1/2 flex items-center">
            <span
                style="
                    font-family: Inter, sans-serif;
                    font-weight: 700;
                    font-size: 20px;
                    color: var(--color-primary);
                    letter-spacing: 0.08em;
                "
            >
                TUT'S
            </span>
        </div>

        <div class="flex items-center gap-5 flex-1 justify-end">
            <button
                aria-label="Pesquisar"
                class="flex items-center justify-center transition-opacity hover:opacity-60"
                style="
                    background: none;
                    border: none;
                    cursor: pointer;
                    padding: 4px;
                "
            >
                <Search :size="18" :stroke-width="1.8" color="var(--color-text)" />
            </button>

            <div class="flex items-center gap-2">
                <Info :size="14" :stroke-width="1.8" color="var(--color-text)" />
                <span
                    style="
                        font-family: Inter, sans-serif;
                        font-weight: 500;
                        font-size: 12px;
                        color: var(--color-text);
                        letter-spacing: 0.02em;
                    "
                >
                    Responsible AI
                </span>
                <span
                    style="
                        width: 40px;
                        height: 22px;
                        border-radius: 11px;
                        background: var(--color-primary);
                        display: inline-flex;
                        align-items: center;
                        justify-content: flex-end;
                        padding-right: 3px;
                    "
                >
                    <span
                        style="
                            width: 16px;
                            height: 16px;
                            border-radius: 50%;
                            background: var(--color-primary-contrast);
                            box-shadow: 0 1px 3px var(--color-border-strong);
                        "
                    />
                </span>
            </div>

            <div style="position: relative">
                <button
                    aria-label="Notificações"
                    class="flex items-center justify-center transition-opacity hover:opacity-60"
                    style="
                        background: none;
                        border: none;
                        cursor: pointer;
                        padding: 4px;
                        position: relative;
                    "
                        @click="toggleNotifications"
                >
                    <Bell :size="20" :stroke-width="1.8" color="var(--color-text)" />

                    <span
                        v-if="unreadCount > 0"
                        style="
                            position: absolute;
                            top: -4px;
                            right: -6px;
                            min-width: 18px;
                            height: 18px;
                            border-radius: 999px;
                            background: var(--color-primary);
                            border: 2px solid var(--color-surface);
                            color: var(--color-primary-contrast);
                            font-family: Inter, sans-serif;
                            font-size: 10px;
                            font-weight: 800;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            padding: 0 4px;
                        "
                    >
                        {{ unreadCount > 9 ? "9+" : unreadCount }}
                    </span>
                </button>

                <div
                    v-if="notificationsOpen"
                    style="position: fixed; inset: 0; z-index: 30"
                    @click="closeNotifications"
                />

                <div
                    v-if="notificationsOpen"
                    style="
                        position: absolute;
                        top: 34px;
                        right: 0;
                        width: 350px;
                        background: var(--color-surface);
                        border: 1px solid var(--color-border);
                        border-radius: 16px;
                        box-shadow: var(--shadow-card);
                        z-index: 60;
                        overflow: hidden;
                    "
                    @click.stop
                >
                    <div
                        style="
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            padding: 14px 16px;
                            border-bottom: 1px solid var(--color-border-soft);
                        "
                    >
                        <div
                            style="
                                display: flex;
                                flex-direction: column;
                                gap: 2px;
                            "
                        >
                            <span
                                style="
                                    font-family: Inter, sans-serif;
                                    font-weight: 800;
                                    font-size: 14px;
                                    color: var(--color-text);
                                "
                            >
                                Notificações
                            </span>
                            <span
                                style="
                                    font-family: Inter, sans-serif;
                                    font-weight: 400;
                                    font-size: 11px;
                                    color: var(--color-text-soft);
                                "
                            >
                                {{ unreadCount }} por ler
                            </span>
                        </div>

                        <button
                            v-if="unreadCount > 0"
                            style="
                                border: none;
                                background: none;
                                cursor: pointer;
                                font-family: Inter, sans-serif;
                                font-size: 11px;
                                font-weight: 700;
                                color: var(--color-primary);
                            "
                            @click="markAllNotificationsAsRead"
                        >
                            Marcar todas
                        </button>
                    </div>

                    <div
                        v-if="loadingNotifications"
                        style="
                            padding: 18px;
                            font-family: Inter, sans-serif;
                            font-size: 13px;
                            color: var(--color-text-muted);
                        "
                    >
                        A carregar...
                    </div>

                    <div
                        v-else-if="notifications.length === 0"
                        style="
                            padding: 20px;
                            font-family: Inter, sans-serif;
                            font-size: 13px;
                            color: var(--color-text-muted);
                        "
                    >
                        Sem notificações por agora.
                    </div>

                    <div v-else style="max-height: 380px; overflow-y: auto">
                        <button
                            v-for="notification in notifications"
                            :key="notification.id"
                            style="
                                width: 100%;
                                border: none;
                                border-bottom: 1px solid var(--color-border-soft);
                                background: var(--color-surface);
                                cursor: pointer;
                                text-align: left;
                                padding: 13px 16px;
                                display: flex;
                                gap: 10px;
                            "
                            @click="handleNotificationClick(notification)"
                        >
                            <span
                                :style="{
                                    width: '8px',
                                    height: '8px',
                                    borderRadius: '50%',
                                    marginTop: '6px',
                                    flexShrink: 0,
                                    background: notification.is_read
                                        ? 'var(--color-border-strong)'
                                        : 'var(--color-primary)',
                                }"
                            />

                            <span
                                style="
                                    display: flex;
                                    flex-direction: column;
                                    gap: 4px;
                                    min-width: 0;
                                "
                            >
                                <span
                                    style="
                                        display: flex;
                                        align-items: center;
                                        justify-content: space-between;
                                        gap: 8px;
                                    "
                                >
                                    <span
                                        style="
                                            font-family: Inter, sans-serif;
                                            font-weight: 800;
                                            font-size: 13px;
                                            color: var(--color-text);
                                            line-height: 1.3;
                                        "
                                    >
                                        {{ notification.title }}
                                    </span>

                                    <span
                                        v-if="notification.created_at"
                                        style="
                                            font-family: Inter, sans-serif;
                                            font-weight: 400;
                                            font-size: 10px;
                                            color: var(--color-text-soft);
                                            white-space: nowrap;
                                        "
                                    >
                                        {{
                                            formatDate(notification.created_at)
                                        }}
                                    </span>
                                </span>

                                <span
                                    v-if="notification.body"
                                    style="
                                        font-family: Inter, sans-serif;
                                        font-weight: 400;
                                        font-size: 12px;
                                        color: var(--color-text-muted);
                                        line-height: 1.35;
                                    "
                                >
                                    {{ notification.body }}
                                </span>

                                <span
                                    v-if="notification.type"
                                    style="
                                        font-family: Inter, sans-serif;
                                        font-weight: 700;
                                        font-size: 10px;
                                        color: var(--color-primary);
                                        letter-spacing: 0.04em;
                                        text-transform: uppercase;
                                        margin-top: 2px;
                                    "
                                >
                                    {{ notification.type }}
                                </span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <div style="width: 1px; height: 28px; background: var(--color-border)" />

            <div
                class="flex items-center gap-3 rounded-lg"
                style="padding: 6px 8px 6px 6px; text-align: left"
            >
                <div
                    class="flex items-center justify-center rounded-full flex-shrink-0"
                    :style="{
                        width: '38px',
                        height: '38px',
                        background: avatar.avatarBg,
                    }"
                >
                    <span
                        :style="{
                            fontFamily: 'Inter, sans-serif',
                            fontWeight: 700,
                            fontSize: `${avatar.fontSize}px`,
                            color: 'var(--color-primary-contrast)',
                        }"
                    >
                        {{ avatar.initials }}
                    </span>
                </div>

                <div class="hidden md:flex flex-col">
                    <span
                        style="
                            font-family: Inter, sans-serif;
                            font-weight: 700;
                            font-size: 12px;
                            color: var(--color-text);
                            line-height: 1.2;
                        "
                    >
                        {{ avatar.name }}
                    </span>
                    <span
                        style="
                            font-family: Inter, sans-serif;
                            font-weight: 400;
                            font-size: 11px;
                            color: var(--color-text-soft);
                        "
                    >
                        {{ avatar.subtitle }}
                    </span>
                </div>
            </div>
        </div>
    </header>
</template>
