<template>
  <div
    v-if="didError"
    :class="['inline-block bg-[var(--color-bg-muted)] text-center align-middle', attrs.class as string]"
    :style="attrs.style as StyleValue"
  >
    <div class="flex items-center justify-center w-full h-full">
      <img
        :src="ERROR_IMG_SRC"
        alt="Error loading image"
        v-bind="restAttrs"
        :data-original-url="src"
      />
    </div>
  </div>

  <img
    v-else
    :src="src"
    :alt="alt"
    v-bind="restAttrs"
    @error="handleError"
  />
</template>

<script setup lang="ts">
import { ref, useAttrs, computed } from 'vue'
import type { StyleValue } from 'vue'

const props = defineProps<{
  src?:   string
  alt?:   string
}>()

const ERROR_IMG_SRC =
  'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODgiIGhlaWdodD0iODgiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgc3Ryb2tlPSIjMDAwIiBzdHJva2UtbGluZWpvaW49InJvdW5kIiBvcGFjaXR5PSIuMyIgZmlsbD0ibm9uZSIgc3Ryb2tlLXdpZHRoPSIzLjciPjxyZWN0IHg9IjE2IiB5PSIxNiIgd2lkdGg9IjU2IiBoZWlnaHQ9IjU2IiByeD0iNiIvPjxwYXRoIGQ9Im0xNiA1OCAxNi0xOCAzMiAzMiIvPjxjaXJjbGUgY3g9IjUzIiBjeT0iMzUiIHI9IjciLz48L3N2Zz4KCg=='

const didError = ref(false)

function handleError() {
  didError.value = true
}

defineOptions({ inheritAttrs: false })

const attrs = useAttrs()

const restAttrs = computed(() => {
  const { class: _class, style: _style, ...rest } = attrs
  return rest
})
</script>
