<script setup lang="ts">
import { computed, type Component } from "vue";
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
import type { TutsNotification } from "@/app/services/notifications";

const props = defineProps<{
    notifications: TutsNotification[];
    unreadCount: number;
    loading: boolean;
    error: string | null;
}>();

const emit = defineEmits<{
    retry: [];
    "mark-all-read": [];
    "mark-read": [notification: TutsNotification];
    "notification-click": [notification: TutsNotification];
    delete: [notification: TutsNotification];
    "view-all": [];
}>();

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

const readableCount = computed(() =>
    props.unreadCount === 1
        ? "1 por ler"
        : `${props.unreadCount} por ler`,
);

function iconFor(notification: TutsNotification): Component {
    return (
        iconByName[notification.icon] ??
        iconByName[notification.type] ??
        Bell
    );
}

function toneClass(notification: TutsNotification): string {
    const tone = String(notification.tone || "neutral");

    return `notification-item-icon--${tone}`;
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
            month: "short",
            hour: "2-digit",
            minute: "2-digit",
        }).format(new Date(notification.created_at));
    } catch {
        return "";
    }
}
</script>

<template>
    <section
        class="notification-dropdown"
        role="dialog"
        aria-label="Notificações recentes"
    >
        <header class="notification-dropdown-header">
            <div>
                <p class="notification-eyebrow">Centro TUTS</p>
                <h2 class="notification-title">Notificações</h2>
                <p class="notification-subtitle">{{ readableCount }}</p>
            </div>

            <button
                v-if="unreadCount > 0"
                type="button"
                class="notification-text-button"
                @click="emit('mark-all-read')"
            >
                Marcar todas
            </button>
        </header>

        <div v-if="loading" class="notification-list notification-list--loading">
            <div
                v-for="index in 4"
                :key="index"
                class="notification-skeleton"
                aria-hidden="true"
            >
                <span class="notification-skeleton-icon" />
                <span class="notification-skeleton-body">
                    <span />
                    <span />
                </span>
            </div>
        </div>

        <div v-else-if="error" class="notification-state">
            <span class="notification-state-icon notification-state-icon--error">
                <AlertCircle :size="20" :stroke-width="1.8" />
            </span>

            <div>
                <p class="notification-state-title">
                    Não foi possível carregar
                </p>
                <p class="notification-state-copy">{{ error }}</p>
            </div>

            <button
                type="button"
                class="notification-action-button"
                @click="emit('retry')"
            >
                <RefreshCcw :size="14" :stroke-width="2" />
                Tentar novamente
            </button>
        </div>

        <div v-else-if="notifications.length === 0" class="notification-state">
            <span class="notification-state-icon">
                <Bell :size="20" :stroke-width="1.8" />
            </span>

            <div>
                <p class="notification-state-title">
                    Sem notificações por agora
                </p>
                <p class="notification-state-copy">
                    Quando houver novidades, aparecem aqui.
                </p>
            </div>
        </div>

        <TransitionGroup
            v-else
            name="notification-item"
            tag="div"
            class="notification-list"
        >
            <article
                v-for="notification in notifications"
                :key="notification.id"
                :class="[
                    'notification-item',
                    notification.is_read ? 'is-read' : 'is-unread',
                ]"
            >
                <button
                    type="button"
                    class="notification-item-main"
                    @click="emit('notification-click', notification)"
                >
                    <span
                        :class="[
                            'notification-item-icon',
                            toneClass(notification),
                        ]"
                    >
                        <component
                            :is="iconFor(notification)"
                            :size="17"
                            :stroke-width="1.9"
                        />
                    </span>

                    <span class="notification-item-content">
                        <span class="notification-item-topline">
                            <span class="notification-item-title">
                                {{ notification.title }}
                            </span>

                            <span
                                v-if="timeLabel(notification)"
                                class="notification-time"
                            >
                                {{ timeLabel(notification) }}
                            </span>
                        </span>

                        <span
                            v-if="notification.body"
                            class="notification-item-body"
                        >
                            {{ notification.body }}
                        </span>

                        <span class="notification-item-meta">
                            {{ notification.type }}
                            <span
                                v-if="!notification.is_read"
                                class="notification-unread-dot"
                                aria-label="Por ler"
                            />
                        </span>
                    </span>
                </button>

                <div class="notification-item-actions">
                    <button
                        v-if="!notification.is_read"
                        type="button"
                        class="notification-icon-button"
                        aria-label="Marcar como lida"
                        @click="emit('mark-read', notification)"
                    >
                        <CheckCircle :size="15" :stroke-width="2" />
                    </button>

                    <button
                        type="button"
                        class="notification-icon-button notification-icon-button--danger"
                        aria-label="Apagar notificação"
                        @click="emit('delete', notification)"
                    >
                        <Trash2 :size="15" :stroke-width="2" />
                    </button>
                </div>
            </article>
        </TransitionGroup>

        <footer class="notification-dropdown-footer">
            <button
                type="button"
                class="notification-view-all"
                @click="emit('view-all')"
            >
                Ver todas
            </button>
        </footer>
    </section>
</template>

<style scoped>
.notification-dropdown {
    position: absolute;
    top: 38px;
    right: 0;
    width: min(390px, calc(100vw - 24px));
    max-height: min(620px, calc(100vh - 96px));
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid var(--color-border);
    border-radius: 14px;
    background: color-mix(in srgb, var(--color-surface) 94%, transparent);
    box-shadow: var(--shadow-card);
    color: var(--color-text);
    z-index: 70;
    backdrop-filter: blur(16px);
}

.notification-dropdown-header,
.notification-dropdown-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 14px 16px;
}

.notification-dropdown-header {
    border-bottom: 1px solid var(--color-border-soft);
}

.notification-dropdown-footer {
    border-top: 1px solid var(--color-border-soft);
}

.notification-eyebrow,
.notification-subtitle,
.notification-title {
    margin: 0;
    font-family: Inter, sans-serif;
}

.notification-eyebrow {
    font-size: 10px;
    font-weight: 800;
    color: var(--color-primary);
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.notification-title {
    margin-top: 2px;
    font-size: 15px;
    font-weight: 800;
    line-height: 1.2;
    color: var(--color-text);
}

.notification-subtitle {
    margin-top: 2px;
    font-size: 12px;
    color: var(--color-text-soft);
}

.notification-text-button,
.notification-view-all,
.notification-action-button,
.notification-icon-button,
.notification-item-main {
    border: 0;
    font-family: Inter, sans-serif;
    cursor: pointer;
}

.notification-text-button,
.notification-view-all {
    padding: 0;
    background: transparent;
    color: var(--color-primary);
    font-size: 12px;
    font-weight: 800;
}

.notification-action-button {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    width: fit-content;
    padding: 8px 10px;
    border-radius: 8px;
    background: var(--color-primary-soft);
    color: var(--color-primary);
    font-size: 12px;
    font-weight: 800;
}

.notification-list {
    max-height: 430px;
    overflow-y: auto;
}

.notification-list--loading {
    padding: 10px 12px;
}

.notification-skeleton {
    display: flex;
    gap: 12px;
    padding: 11px 4px;
}

.notification-skeleton-icon,
.notification-skeleton-body span {
    display: block;
    border-radius: 999px;
    background: linear-gradient(
        90deg,
        var(--color-surface-muted),
        var(--color-border-soft),
        var(--color-surface-muted)
    );
    background-size: 180% 100%;
    animation: notification-shimmer 1.25s ease-in-out infinite;
}

.notification-skeleton-icon {
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
}

.notification-skeleton-body {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 8px;
    padding-top: 3px;
}

.notification-skeleton-body span:first-child {
    width: 76%;
    height: 11px;
}

.notification-skeleton-body span:last-child {
    width: 54%;
    height: 10px;
}

.notification-state {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
    padding: 28px 18px;
}

.notification-state-icon,
.notification-item-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.notification-state-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: var(--color-primary-soft);
    color: var(--color-primary);
}

.notification-state-icon--error {
    background: var(--color-danger-soft);
    color: var(--color-danger);
}

.notification-state-title,
.notification-state-copy {
    margin: 0;
    font-family: Inter, sans-serif;
}

.notification-state-title {
    font-size: 14px;
    font-weight: 800;
    color: var(--color-text);
}

.notification-state-copy {
    margin-top: 4px;
    font-size: 13px;
    line-height: 1.45;
    color: var(--color-text-muted);
}

.notification-item {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    border-bottom: 1px solid var(--color-border-soft);
    background: var(--color-surface);
}

.notification-item.is-unread {
    background: color-mix(in srgb, var(--color-primary-soft) 28%, var(--color-surface));
}

.notification-item:last-child {
    border-bottom: 0;
}

.notification-item-main {
    display: flex;
    min-width: 0;
    gap: 12px;
    padding: 13px 8px 13px 16px;
    background: transparent;
    color: inherit;
    text-align: left;
}

.notification-item-main:hover {
    background: var(--color-surface-muted);
}

.notification-item-icon {
    width: 34px;
    height: 34px;
    margin-top: 1px;
    border-radius: 10px;
}

.notification-item-icon--neutral {
    background: var(--color-surface-muted);
    color: var(--color-text-muted);
}

.notification-item-icon--info {
    background: var(--color-info-soft);
    color: var(--color-info);
}

.notification-item-icon--primary {
    background: var(--color-primary-soft);
    color: var(--color-primary);
}

.notification-item-icon--success {
    background: var(--color-success-soft);
    color: var(--color-success);
}

.notification-item-icon--warning {
    background: var(--color-warning-soft);
    color: var(--color-warning);
}

.notification-item-icon--danger {
    background: var(--color-danger-soft);
    color: var(--color-danger);
}

.notification-item-content {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    gap: 5px;
}

.notification-item-topline {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.notification-item-title,
.notification-time,
.notification-item-body,
.notification-item-meta {
    font-family: Inter, sans-serif;
}

.notification-item-title {
    min-width: 0;
    font-size: 13px;
    font-weight: 800;
    line-height: 1.35;
    color: var(--color-text);
}

.notification-time {
    flex: 0 0 auto;
    font-size: 10px;
    color: var(--color-text-soft);
    white-space: nowrap;
}

.notification-item-body {
    display: -webkit-box;
    overflow: hidden;
    color: var(--color-text-muted);
    font-size: 12px;
    line-height: 1.4;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.notification-item-meta {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: var(--color-primary);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.notification-unread-dot {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: var(--color-primary);
}

.notification-item-actions {
    display: flex;
    align-items: center;
    gap: 3px;
    padding-right: 9px;
}

.notification-icon-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: transparent;
    color: var(--color-text-soft);
}

.notification-icon-button:hover {
    background: var(--color-surface-muted);
    color: var(--color-primary);
}

.notification-icon-button--danger:hover {
    color: var(--color-danger);
}

.notification-view-all {
    width: 100%;
    padding: 2px 0;
}

.notification-text-button:focus-visible,
.notification-view-all:focus-visible,
.notification-action-button:focus-visible,
.notification-icon-button:focus-visible,
.notification-item-main:focus-visible {
    outline: 3px solid var(--ring-focus);
    outline-offset: 2px;
}

.notification-item-enter-active,
.notification-item-leave-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}

.notification-item-enter-from,
.notification-item-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

@keyframes notification-shimmer {
    0% {
        background-position: 120% 0;
    }

    100% {
        background-position: -120% 0;
    }
}

@media (max-width: 640px) {
    .notification-dropdown {
        position: fixed;
        top: 76px;
        right: 12px;
        left: 92px;
        width: auto;
        max-height: calc(100vh - 92px);
    }

    .notification-item {
        grid-template-columns: minmax(0, 1fr);
    }

    .notification-item-actions {
        justify-content: flex-end;
        padding: 0 12px 10px;
    }
}
</style>
