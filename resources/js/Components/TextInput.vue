<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-md border-[var(--color-border)] bg-[var(--color-surface-inset)] text-[var(--color-text)] shadow-sm placeholder:text-[var(--color-text-soft)] focus:border-[var(--color-primary)] focus:ring-[var(--ring-focus)]"
        v-model="model"
        ref="input"
    />
</template>
