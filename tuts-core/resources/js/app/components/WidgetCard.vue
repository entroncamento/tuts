<script setup lang="ts">
defineOptions({ name: 'WidgetCard' })

defineProps<{
  title: string
  actionLabel?: string
}>()

const emit = defineEmits<{
  action: []
}>()
</script>

<template>
  <div
    style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 16px;"
  >
    <!-- Header row -->
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
      <span
        style="font-family: Inter, sans-serif; font-weight: 700; font-size: 14px; color: #1E1E1E;"
      >
        {{ title }}
      </span>
      <button
        v-if="actionLabel"
        class="transition-opacity hover:opacity-70"
        style="display: inline-flex; align-items: center; font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #009957; background: none; border: none; cursor: pointer; padding: 0;"
        @click="emit('action')"
      >
        {{ actionLabel }}
      </button>
    </div>

    <!-- Content slot -->
    <slot />
  </div>
</template>
