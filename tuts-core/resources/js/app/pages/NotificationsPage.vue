<script setup lang="ts">
import { computed, onMounted, ref, watch, type Component } from "vue";
import { useRouter } from "vue-router";
import {
    AlertCircle,
    AlertTriangle,
    Bell,
    BookOpen,
    Brain,
    CheckCircle,
    Clock,
    MessageCircle,
    RefreshCcw,
    Trash2,
} from "@lucide/vue";
import { useNotifications } from "@/app/composables/useNotifications";
import type { TutsNotification } from "@/app/services/notifications";

type NotificationFilter = "all" | "unread";

const router = useRouter();
const filter = ref<NotificationFilter>("all");
const workingId = ref<number | null>(null);

const {
    notifications,
    unreadCount,
    loading,
    error,
    loadNotifications,
    markAsRead,
    markAllAsRead,
    removeNotification,
} = useNotifications();

const iconByName: Record<string, Component> = {
    "alert-circle": AlertCircle,
    "alert-triangle": AlertTriangle,
    bell: Bell,
    "book-open": BookOpen,
    brain: Brain,
    "check-circle": CheckCircle,
    clock: Clock,
    "message-circle": MessageCircle,
};

const visibleNotifications = computed(() =>
    filter.value === "unread"
        ? notifications.value.filter((notification) => !notification.is_read)
        : notifications.value,
);

const filterLabel = computed(() =>
    filter.value === "unread" ? "Por ler" : "Todas",
);

async function loadPageNotifications(): Promise<void> {
    try {
        await loadNotifications({
            limit: 50,
            unreadOnly: filter.value === "unread",
        });
    } catch (requestError) {
        console.error("[TUTS] Falha ao carregar notificações.", requestError);
    }
}

function iconFor(notification: TutsNotification): Component {
    return (
        iconByName[notification.icon] ??
        iconByName[notification.type] ??
        Bell
    );
}

function toneClass(notification: TutsNotification): string {
    return `notifications-page-icon--${notification.tone || "neutral"}`;
}

function timeLabel(notification: TutsNotification): string {
    if (notification.created_at_human) {
        return notification.created_at_human;
    }

    if (!notification.created_at) {
        return "";
    }

    try {
        return new Intl.DateTimeFormat("pt-PT", {
            day: "2-digit",
            month: "long",
            hour: "2-digit",
            minute: "2-digit",
        }).format(new Date(notification.created_at));
    } catch {
        return "";
    }
}

async function handleNotificationClick(
    notification: TutsNotification,
): Promise<void> {
    workingId.value = notification.id;

    try {
        await markAsRead(notification.id);

        if (notification.url) {
            await router.push(notification.url);
        }
    } catch (requestError) {
        console.error("[TUTS] Falha ao abrir notificação.", requestError);
    } finally {
        workingId.value = null;
    }
}

async function handleMarkAllAsRead(): Promise<void> {
    try {
        await markAllAsRead();
    } catch (requestError) {
        console.error("[TUTS] Falha ao marcar todas como lidas.", requestError);
    }
}

async function handleMarkAsRead(notification: TutsNotification): Promise<void> {
    workingId.value = notification.id;

    try {
        await markAsRead(notification.id);
    } catch (requestError) {
        console.error("[TUTS] Falha ao marcar notificação como lida.", requestError);
    } finally {
        workingId.value = null;
    }
}

async function handleDelete(notification: TutsNotification): Promise<void> {
    workingId.value = notification.id;

    try {
        await removeNotification(notification.id);
    } catch (requestError) {
        console.error("[TUTS] Falha ao apagar notificação.", requestError);
    } finally {
        workingId.value = null;
    }
}

watch(filter, () => {
    void loadPageNotifications();
});

onMounted(() => {
    void loadPageNotifications();
});
</script>

<template>
    <div class="notifications-page">
        <div class="notifications-page-inner">
            <header class="notifications-hero">
                <div>
                    <p class="notifications-eyebrow">Centro TUTS</p>
                    <h1 class="notifications-title">Notificações</h1>
                    <p class="notifications-subtitle">
                        Acompanha lembretes, alertas de estudo e novidades do sistema.
                    </p>
                </div>

                <div class="notifications-header-actions">
                    <button
                        type="button"
                        class="notifications-secondary-button"
                        @click="loadPageNotifications"
                    >
                        <RefreshCcw :size="15" :stroke-width="2" />
                        Atualizar
                    </button>

                    <button
                        type="button"
                        class="notifications-primary-button"
                        :disabled="unreadCount === 0"
                        @click="handleMarkAllAsRead"
                    >
                        <CheckCircle :size="15" :stroke-width="2" />
                        Marcar todas como lidas
                    </button>
                </div>
            </header>

            <section class="notifications-toolbar" aria-label="Filtros">
                <div class="notifications-tabs">
                    <button
                        type="button"
                        :class="[
                            'notifications-tab',
                            filter === 'all' ? 'is-active' : '',
                        ]"
                        @click="filter = 'all'"
                    >
                        Todas
                    </button>

                    <button
                        type="button"
                        :class="[
                            'notifications-tab',
                            filter === 'unread' ? 'is-active' : '',
                        ]"
                        @click="filter = 'unread'"
                    >
                        Por ler
                        <span v-if="unreadCount > 0" class="notifications-tab-count">
                            {{ unreadCount > 99 ? "99+" : unreadCount }}
                        </span>
                    </button>
                </div>

                <p class="notifications-toolbar-copy">
                    {{ visibleNotifications.length }} em {{ filterLabel.toLowerCase() }}
                </p>
            </section>

            <section class="notifications-list-shell">
                <div v-if="loading" class="notifications-loading">
                    <div
                        v-for="index in 6"
                        :key="index"
                        class="notifications-skeleton"
                    >
                        <span />
                        <div>
                            <span />
                            <span />
                        </div>
                    </div>
                </div>

                <div v-else-if="error" class="notifications-state">
                    <span class="notifications-state-icon notifications-state-icon--error">
                        <AlertCircle :size="24" :stroke-width="1.8" />
                    </span>

                    <h2>Não foi possível carregar</h2>
                    <p>{{ error }}</p>

                    <button
                        type="button"
                        class="notifications-secondary-button"
                        @click="loadPageNotifications"
                    >
                        <RefreshCcw :size="15" :stroke-width="2" />
                        Tentar novamente
                    </button>
                </div>

                <div
                    v-else-if="visibleNotifications.length === 0"
                    class="notifications-state"
                >
                    <span class="notifications-state-icon">
                        <Bell :size="24" :stroke-width="1.8" />
                    </span>

                    <h2>Sem notificações por agora</h2>
                    <p>Quando houver novidades, aparecem aqui.</p>
                </div>

                <TransitionGroup
                    v-else
                    name="notifications-row"
                    tag="div"
                    class="notifications-list"
                >
                    <article
                        v-for="notification in visibleNotifications"
                        :key="notification.id"
                        :class="[
                            'notifications-row',
                            notification.is_read ? 'is-read' : 'is-unread',
                        ]"
                    >
                        <button
                            type="button"
                            class="notifications-row-main"
                            :disabled="workingId === notification.id"
                            @click="handleNotificationClick(notification)"
                        >
                            <span
                                :class="[
                                    'notifications-page-icon',
                                    toneClass(notification),
                                ]"
                            >
                                <component
                                    :is="iconFor(notification)"
                                    :size="20"
                                    :stroke-width="1.9"
                                />
                            </span>

                            <span class="notifications-row-content">
                                <span class="notifications-row-kicker">
                                    {{ notification.type }}
                                    <span
                                        v-if="!notification.is_read"
                                        class="notifications-unread-pill"
                                    >
                                        Por ler
                                    </span>
                                </span>

                                <span class="notifications-row-title">
                                    {{ notification.title }}
                                </span>

                                <span
                                    v-if="notification.body"
                                    class="notifications-row-body"
                                >
                                    {{ notification.body }}
                                </span>
                            </span>

                            <span
                                v-if="timeLabel(notification)"
                                class="notifications-row-time"
                            >
                                {{ timeLabel(notification) }}
                            </span>
                        </button>

                        <div class="notifications-row-actions">
                            <button
                                v-if="!notification.is_read"
                                type="button"
                                class="notifications-icon-button"
                                aria-label="Marcar como lida"
                                :disabled="workingId === notification.id"
                                @click="handleMarkAsRead(notification)"
                            >
                                <CheckCircle :size="16" :stroke-width="2" />
                            </button>

                            <button
                                type="button"
                                class="notifications-icon-button notifications-icon-button--danger"
                                aria-label="Apagar notificação"
                                :disabled="workingId === notification.id"
                                @click="handleDelete(notification)"
                            >
                                <Trash2 :size="16" :stroke-width="2" />
                            </button>
                        </div>
                    </article>
                </TransitionGroup>
            </section>
        </div>
    </div>
</template>

<style scoped>
.notifications-page {
    height: 100%;
    overflow-y: auto;
    background: var(--tuts-bg);
    color: var(--tuts-text);
}

.notifications-page-inner {
    max-width: 1120px;
    margin: 0 auto;
    padding: 38px 24px 120px;
}

.notifications-hero,
.notifications-toolbar,
.notifications-list-shell {
    width: 100%;
}

.notifications-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
}

.notifications-eyebrow,
.notifications-title,
.notifications-subtitle,
.notifications-toolbar-copy {
    margin: 0;
    font-family: Inter, sans-serif;
}

.notifications-eyebrow {
    margin-bottom: 8px;
    color: var(--color-primary);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.notifications-title {
    color: var(--tuts-text);
    font-size: 32px;
    font-weight: 800;
    line-height: 1.15;
}

.notifications-subtitle {
    max-width: 580px;
    margin-top: 8px;
    color: var(--tuts-text-muted);
    font-size: 15px;
    line-height: 1.5;
}

.notifications-header-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
}

.notifications-primary-button,
.notifications-secondary-button,
.notifications-tab,
.notifications-icon-button,
.notifications-row-main {
    border: 0;
    font-family: Inter, sans-serif;
    cursor: pointer;
}

.notifications-primary-button,
.notifications-secondary-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 36px;
    padding: 0 13px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 800;
    transition:
        background 0.18s ease,
        color 0.18s ease,
        opacity 0.18s ease;
}

.notifications-primary-button {
    background: var(--color-primary);
    color: var(--color-primary-contrast);
}

.notifications-secondary-button {
    background: var(--color-surface-muted);
    color: var(--color-text);
}

.notifications-primary-button:disabled,
.notifications-secondary-button:disabled,
.notifications-icon-button:disabled,
.notifications-row-main:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

.notifications-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
}

.notifications-tabs {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px;
    border: 1px solid var(--color-border-soft);
    border-radius: 10px;
    background: var(--color-surface);
}

.notifications-tab {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 30px;
    padding: 0 12px;
    border-radius: 7px;
    background: transparent;
    color: var(--color-text-muted);
    font-size: 12px;
    font-weight: 800;
}

.notifications-tab.is-active {
    background: var(--color-primary-soft);
    color: var(--color-primary);
}

.notifications-tab-count {
    min-width: 18px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    border-radius: 999px;
    background: var(--color-primary);
    color: var(--color-primary-contrast);
    font-size: 10px;
}

.notifications-toolbar-copy {
    color: var(--color-text-soft);
    font-size: 12px;
}

.notifications-list-shell {
    min-height: 380px;
    overflow: hidden;
    border: 1px solid var(--color-border-soft);
    border-radius: 12px;
    background: var(--color-surface);
    box-shadow: var(--shadow-soft);
}

.notifications-list {
    display: flex;
    flex-direction: column;
}

.notifications-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    border-bottom: 1px solid var(--color-border-soft);
    background: var(--color-surface);
}

.notifications-row:last-child {
    border-bottom: 0;
}

.notifications-row.is-unread {
    background: color-mix(in srgb, var(--color-primary-soft) 24%, var(--color-surface));
}

.notifications-row-main {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) auto;
    align-items: center;
    gap: 14px;
    min-width: 0;
    padding: 16px 6px 16px 18px;
    background: transparent;
    color: inherit;
    text-align: left;
}

.notifications-row-main:hover {
    background: var(--color-surface-muted);
}

.notifications-page-icon,
.notifications-state-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.notifications-page-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
}

.notifications-page-icon--neutral {
    background: var(--color-surface-muted);
    color: var(--color-text-muted);
}

.notifications-page-icon--info {
    background: var(--color-info-soft);
    color: var(--color-info);
}

.notifications-page-icon--primary {
    background: var(--color-primary-soft);
    color: var(--color-primary);
}

.notifications-page-icon--success {
    background: var(--color-success-soft);
    color: var(--color-success);
}

.notifications-page-icon--warning {
    background: var(--color-warning-soft);
    color: var(--color-warning);
}

.notifications-page-icon--danger {
    background: var(--color-danger-soft);
    color: var(--color-danger);
}

.notifications-row-content {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 5px;
}

.notifications-row-kicker,
.notifications-row-title,
.notifications-row-body,
.notifications-row-time {
    font-family: Inter, sans-serif;
}

.notifications-row-kicker {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--color-primary);
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.notifications-unread-pill {
    padding: 2px 6px;
    border-radius: 999px;
    background: var(--color-primary-soft);
    color: var(--color-primary);
    font-size: 9px;
    letter-spacing: 0;
    text-transform: none;
}

.notifications-row-title {
    color: var(--color-text);
    font-size: 15px;
    font-weight: 800;
    line-height: 1.35;
}

.notifications-row-body {
    max-width: 720px;
    color: var(--color-text-muted);
    font-size: 13px;
    line-height: 1.45;
}

.notifications-row-time {
    align-self: start;
    color: var(--color-text-soft);
    font-size: 12px;
    white-space: nowrap;
}

.notifications-row-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    padding-right: 14px;
}

.notifications-icon-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: transparent;
    color: var(--color-text-soft);
}

.notifications-icon-button:hover {
    background: var(--color-surface-muted);
    color: var(--color-primary);
}

.notifications-icon-button--danger:hover {
    color: var(--color-danger);
}

.notifications-state,
.notifications-loading {
    min-height: 380px;
}

.notifications-state {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 12px;
    padding: 42px;
}

.notifications-state-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: var(--color-primary-soft);
    color: var(--color-primary);
}

.notifications-state-icon--error {
    background: var(--color-danger-soft);
    color: var(--color-danger);
}

.notifications-state h2,
.notifications-state p {
    margin: 0;
    font-family: Inter, sans-serif;
}

.notifications-state h2 {
    color: var(--color-text);
    font-size: 18px;
    font-weight: 800;
}

.notifications-state p {
    max-width: 420px;
    color: var(--color-text-muted);
    font-size: 14px;
    line-height: 1.45;
}

.notifications-loading {
    padding: 14px 18px;
}

.notifications-skeleton {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr);
    gap: 14px;
    padding: 16px 0;
    border-bottom: 1px solid var(--color-border-soft);
}

.notifications-skeleton:last-child {
    border-bottom: 0;
}

.notifications-skeleton > span,
.notifications-skeleton div span {
    display: block;
    border-radius: 999px;
    background: linear-gradient(
        90deg,
        var(--color-surface-muted),
        var(--color-border-soft),
        var(--color-surface-muted)
    );
    background-size: 180% 100%;
    animation: notifications-shimmer 1.25s ease-in-out infinite;
}

.notifications-skeleton > span {
    width: 42px;
    height: 42px;
}

.notifications-skeleton div {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding-top: 4px;
}

.notifications-skeleton div span:first-child {
    width: 58%;
    height: 12px;
}

.notifications-skeleton div span:last-child {
    width: 78%;
    height: 11px;
}

.notifications-primary-button:focus-visible,
.notifications-secondary-button:focus-visible,
.notifications-tab:focus-visible,
.notifications-icon-button:focus-visible,
.notifications-row-main:focus-visible {
    outline: 3px solid var(--ring-focus);
    outline-offset: 2px;
}

.notifications-row-enter-active,
.notifications-row-leave-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}

.notifications-row-enter-from,
.notifications-row-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

@keyframes notifications-shimmer {
    0% {
        background-position: 120% 0;
    }

    100% {
        background-position: -120% 0;
    }
}

@media (max-width: 760px) {
    .notifications-page-inner {
        padding: 26px 16px 120px;
    }

    .notifications-hero,
    .notifications-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .notifications-header-actions {
        justify-content: flex-start;
    }

    .notifications-row {
        grid-template-columns: minmax(0, 1fr);
    }

    .notifications-row-main {
        grid-template-columns: 42px minmax(0, 1fr);
        padding: 15px 16px 4px;
    }

    .notifications-row-time {
        grid-column: 2;
        white-space: normal;
    }

    .notifications-row-actions {
        justify-content: flex-end;
        padding: 0 16px 14px;
    }
}
</style>
