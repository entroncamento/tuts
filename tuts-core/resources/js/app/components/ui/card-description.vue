<script setup lang="ts">
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{ class?: string }>()
</script>

<template>
  <p
    data-slot="card-description"
    :class="cn('text-muted-foreground', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </p>
</template>
