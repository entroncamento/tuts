import { apiFetch } from "@/app/services/api";

export type NotificationType =
    | "reminder"
    | "system"
    | "study"
    | "chat"
    | "rag"
    | "success"
    | "warning"
    | "error";

export type NotificationTone =
    | "neutral"
    | "info"
    | "primary"
    | "success"
    | "warning"
    | "danger";

export interface TutsNotification {
    id: number;
    type: NotificationType | string;
    title: string;
    body: string | null;
    url: string | null;
    icon: string;
    tone: NotificationTone | string;
    read_at: string | null;
    created_at: string | null;
    created_at_human: string | null;
    data: Record<string, unknown>;
    scheduled_for?: string | null;
    is_read: boolean;
}

export interface NotificationsResponse {
    status: string;
    notifications: TutsNotification[];
    unread_count: number;
}

export interface UnreadCountResponse {
    status: string;
    unread_count: number;
}

export interface NotificationMutationResponse extends UnreadCountResponse {
    notification?: TutsNotification;
}

export interface FetchNotificationsOptions {
    limit?: number;
    unreadOnly?: boolean;
}

function buildNotificationsUrl(options: FetchNotificationsOptions = {}): string {
    const params = new URLSearchParams();

    if (options.limit) {
        params.set("limit", String(options.limit));
    }

    if (options.unreadOnly) {
        params.set("unread_only", "1");
    }

    const query = params.toString();

    return `/api/notifications${query ? `?${query}` : ""}`;
}

export async function fetchNotifications(
    options: FetchNotificationsOptions = {},
): Promise<NotificationsResponse> {
    return await apiFetch<NotificationsResponse>(buildNotificationsUrl(options));
}

export async function fetchUnreadCount(): Promise<UnreadCountResponse> {
    return await apiFetch<UnreadCountResponse>("/api/notifications/unread-count");
}

export async function markNotificationAsRead(
    id: number | string,
): Promise<NotificationMutationResponse> {
    return await apiFetch<NotificationMutationResponse>(
        `/api/notifications/${id}/read`,
        {
            method: "PATCH",
        },
    );
}

export async function markAllNotificationsAsRead(): Promise<UnreadCountResponse> {
    return await apiFetch<UnreadCountResponse>("/api/notifications/read-all", {
        method: "PATCH",
    });
}

export async function deleteNotification(
    id: number | string,
): Promise<UnreadCountResponse> {
    return await apiFetch<UnreadCountResponse>(`/api/notifications/${id}`, {
        method: "DELETE",
    });
}
