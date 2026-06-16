<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { Bell } from "@lucide/vue";
import NotificationDropdown from "@/app/components/NotificationDropdown.vue";
import { useNotifications } from "@/app/composables/useNotifications";
import { useOutsideClick } from "@/app/composables/useOutsideClick";
import type { TutsNotification } from "@/app/services/notifications";

defineOptions({ name: "NotificationBell" });

const router = useRouter();
const root = ref<HTMLElement | null>(null);
const isOpen = ref(false);

const {
    notifications,
    unreadCount,
    unreadLabel,
    loading,
    error,
    loadNotifications,
    refreshUnreadCount,
    markAsRead,
    markAllAsRead,
    removeNotification,
    startPolling,
    stopPolling,
} = useNotifications();

useOutsideClick(root, closeDropdown, isOpen);

async function openDropdown(): Promise<void> {
    isOpen.value = true;

    try {
        await loadNotifications({ limit: 8 });
    } catch (requestError) {
        console.error("[TUTS] Falha ao carregar notificações.", requestError);
    }
}

async function toggleDropdown(): Promise<void> {
    if (isOpen.value) {
        closeDropdown();
        return;
    }

    await openDropdown();
}

function closeDropdown(): void {
    isOpen.value = false;
}

async function retry(): Promise<void> {
    await openDropdown();
}

async function handleMarkAsRead(notification: TutsNotification): Promise<void> {
    try {
        await markAsRead(notification.id);
    } catch (requestError) {
        console.error("[TUTS] Falha ao marcar notificação como lida.", requestError);
    }
}

async function handleMarkAllAsRead(): Promise<void> {
    try {
        await markAllAsRead();
    } catch (requestError) {
        console.error("[TUTS] Falha ao marcar todas como lidas.", requestError);
    }
}

async function handleDelete(notification: TutsNotification): Promise<void> {
    try {
        await removeNotification(notification.id);
    } catch (requestError) {
        console.error("[TUTS] Falha ao apagar notificação.", requestError);
    }
}

async function handleNotificationClick(
    notification: TutsNotification,
): Promise<void> {
    await handleMarkAsRead(notification);
    closeDropdown();

    if (notification.url) {
        await router.push(notification.url);
    }
}

async function viewAll(): Promise<void> {
    closeDropdown();
    await router.push("/notificacoes");
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === "Escape") {
        closeDropdown();
    }
}

onMounted(() => {
    void refreshUnreadCount();
    startPolling(45000);
    window.addEventListener("keydown", onKeydown);
});

onBeforeUnmount(() => {
    stopPolling();
    window.removeEventListener("keydown", onKeydown);
});
</script>

<template>
    <div ref="root" class="notification-bell">
        <button
            type="button"
            class="notification-bell-button"
            aria-label="Notificações"
            aria-haspopup="dialog"
            :aria-expanded="isOpen"
            @click="toggleDropdown"
        >
            <Bell :size="20" :stroke-width="1.8" />

            <span
                v-if="unreadCount > 0"
                class="notification-bell-badge"
                aria-label="Notificações por ler"
            >
                {{ unreadLabel }}
            </span>
        </button>

        <Transition name="notification-popover">
            <NotificationDropdown
                v-if="isOpen"
                :notifications="notifications"
                :unread-count="unreadCount"
                :loading="loading"
                :error="error"
                @retry="retry"
                @mark-all-read="handleMarkAllAsRead"
                @mark-read="handleMarkAsRead"
                @notification-click="handleNotificationClick"
                @delete="handleDelete"
                @view-all="viewAll"
            />
        </Transition>
    </div>
</template>

<style scoped>
.notification-bell {
    position: relative;
}

.notification-bell-button {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: var(--color-text);
    cursor: pointer;
    transition:
        background 0.18s ease,
        color 0.18s ease,
        transform 0.18s ease;
}

.notification-bell-button:hover {
    background: var(--color-surface-muted);
    color: var(--color-primary);
}

.notification-bell-button:active {
    transform: translateY(1px);
}

.notification-bell-button:focus-visible {
    outline: 3px solid var(--ring-focus);
    outline-offset: 2px;
}

.notification-bell-badge {
    position: absolute;
    top: -4px;
    right: -5px;
    min-width: 18px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    border: 2px solid var(--color-surface);
    border-radius: 999px;
    background: var(--color-primary);
    color: var(--color-primary-contrast);
    font-family: Inter, sans-serif;
    font-size: 10px;
    font-weight: 900;
    line-height: 1;
}

.notification-popover-enter-active,
.notification-popover-leave-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}

.notification-popover-enter-from,
.notification-popover-leave-to {
    opacity: 0;
    transform: translateY(-6px) scale(0.98);
}
</style>
