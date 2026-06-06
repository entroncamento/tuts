<script setup lang="ts">
import "@/styles/index.css";
import "@/styles/theme.css";
import FrontendApp from "@/app/App.vue";
</script>

<template>
    <div class="frontend-root">
        <FrontendApp />
    </div>
</template>

<style scoped>
.frontend-root {
    min-height: 100vh;
    background: var(--tuts-bg);
    color: var(--tuts-text);
}

:global(html),
:global(body),
:global(#app) {
    background: var(--tuts-bg);
    color: var(--tuts-text);
}
</style>
