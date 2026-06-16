<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-[var(--color-primary)] text-start text-base font-medium text-[var(--color-primary-strong)] bg-[var(--color-primary-soft)] focus:outline-none focus:text-[var(--color-primary-strong)] focus:bg-[var(--color-primary-soft)] focus:border-[var(--color-primary-strong)] transition duration-150 ease-in-out'
        : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-[var(--color-text-muted)] hover:text-[var(--color-text)] hover:bg-[var(--color-surface-muted)] hover:border-[var(--color-border-strong)] focus:outline-none focus:text-[var(--color-text)] focus:bg-[var(--color-surface-muted)] focus:border-[var(--color-border-strong)] transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
