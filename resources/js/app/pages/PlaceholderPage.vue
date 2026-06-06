<script setup lang="ts">
// ─── Props ────────────────────────────────────────────────────────────────────
// Receives icon, title, subtitle from the router's route-level props or from
// a parent component. Mirrors the React PlaceholderPage props interface.
defineProps<{
  icon:     string
  title:    string
  subtitle: string
}>()
</script>

<template>
  <div
    style="height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; padding-bottom: 80px;"
  >
    <div
      style="width: 64px; height: 64px; border-radius: 16px; background: #F5F5F5; display: flex; align-items: center; justify-content: center; font-size: 28px;"
    >
      {{ icon }}
    </div>

    <div style="text-align: center;">
      <p
        style="font-family: Inter, sans-serif; font-weight: 600; font-size: 16px; color: #1E1E1E; margin: 0; margin-bottom: 6px;"
      >
        {{ title }}
      </p>
      <p
        style="font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #BDBABA; margin: 0;"
      >
        {{ subtitle }}
      </p>
    </div>
  </div>
</template>
