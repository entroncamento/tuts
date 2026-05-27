<script setup lang="ts">
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{ class?: string }>()
</script>

<template>
  <div
    data-slot="card-content"
    :class="cn('px-6 [&:last-child]:pb-6', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </div>
</template>
