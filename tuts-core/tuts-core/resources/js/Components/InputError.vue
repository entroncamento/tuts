<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-[var(--color-danger)]">
            {{ message }}
        </p>
    </div>
</template>
