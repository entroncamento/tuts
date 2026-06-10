<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-[var(--color-text-muted)] shadow-sm transition duration-150 ease-in-out hover:bg-[var(--color-bg-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--ring-focus)] focus:ring-offset-2 focus:ring-offset-[var(--color-bg)] disabled:opacity-25"
    >
        <slot />
    </button>
</template>
