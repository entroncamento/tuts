<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-[var(--color-text-muted)] transition duration-150 ease-in-out hover:bg-[var(--color-surface-muted)] hover:text-[var(--color-text)] focus:bg-[var(--color-surface-muted)] focus:text-[var(--color-text)] focus:outline-none"
    >
        <slot />
    </Link>
</template>
