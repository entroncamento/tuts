import { computed, ref } from "vue";
import {
    deleteNotification as deleteNotificationRequest,
    fetchNotifications,
    fetchUnreadCount,
    markAllNotificationsAsRead as markAllNotificationsAsReadRequest,
    markNotificationAsRead as markNotificationAsReadRequest,
    type FetchNotificationsOptions,
    type TutsNotification,
} from "@/app/services/notifications";

const notifications = ref<TutsNotification[]>([]);
const unreadCount = ref(0);
const loading = ref(false);
const refreshingCount = ref(false);
const error = ref<string | null>(null);
let pollingId: number | null = null;

function errorMessage(errorValue: unknown): string {
    return errorValue instanceof Error
        ? errorValue.message
        : "Nao foi possivel carregar as notificacoes.";
}

function replaceNotification(updated: TutsNotification): void {
    notifications.value = notifications.value.map((notification) =>
        notification.id === updated.id ? updated : notification,
    );
}

function markLocalNotificationAsRead(id: number | string): void {
    notifications.value = notifications.value.map((notification) =>
        String(notification.id) === String(id)
            ? {
                  ...notification,
                  is_read: true,
                  read_at: notification.read_at ?? new Date().toISOString(),
              }
            : notification,
    );
}

export function useNotifications() {
    const unreadLabel = computed(() =>
        unreadCount.value > 99 ? "99+" : String(unreadCount.value),
    );

    async function loadNotifications(
        options: FetchNotificationsOptions = { limit: 20 },
    ): Promise<void> {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetchNotifications(options);
            notifications.value = response.notifications ?? [];
            unreadCount.value = response.unread_count ?? 0;
        } catch (requestError) {
            error.value = errorMessage(requestError);
            throw requestError;
        } finally {
            loading.value = false;
        }
    }

    async function refreshUnreadCount(): Promise<void> {
        refreshingCount.value = true;

        try {
            const response = await fetchUnreadCount();
            unreadCount.value = response.unread_count ?? 0;
        } catch (requestError) {
            error.value = errorMessage(requestError);
        } finally {
            refreshingCount.value = false;
        }
    }

    async function markAsRead(id: number | string): Promise<void> {
        const current = notifications.value.find(
            (notification) => String(notification.id) === String(id),
        );

        if (current?.is_read) {
            return;
        }

        const response = await markNotificationAsReadRequest(id);

        if (response.notification) {
            replaceNotification(response.notification);
        } else {
            markLocalNotificationAsRead(id);
        }

        unreadCount.value = response.unread_count ?? Math.max(0, unreadCount.value - 1);
    }

    async function markAllAsRead(): Promise<void> {
        const response = await markAllNotificationsAsReadRequest();

        notifications.value = notifications.value.map((notification) => ({
            ...notification,
            is_read: true,
            read_at: notification.read_at ?? new Date().toISOString(),
        }));

        unreadCount.value = response.unread_count ?? 0;
    }

    async function removeNotification(id: number | string): Promise<void> {
        const response = await deleteNotificationRequest(id);

        notifications.value = notifications.value.filter(
            (notification) => String(notification.id) !== String(id),
        );

        unreadCount.value = response.unread_count ?? unreadCount.value;
    }

    function startPolling(intervalMs = 45000): void {
        if (pollingId !== null) {
            return;
        }

        pollingId = window.setInterval(() => {
            void refreshUnreadCount();
        }, intervalMs);
    }

    function stopPolling(): void {
        if (pollingId === null) {
            return;
        }

        window.clearInterval(pollingId);
        pollingId = null;
    }

    return {
        notifications,
        unreadCount,
        unreadLabel,
        loading,
        refreshingCount,
        error,
        loadNotifications,
        refreshUnreadCount,
        markAsRead,
        markAllAsRead,
        removeNotification,
        startPolling,
        stopPolling,
    };
}
