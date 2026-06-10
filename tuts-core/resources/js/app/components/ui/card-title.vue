<script setup lang="ts">
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{ class?: string }>()
</script>

<template>
  <h4
    data-slot="card-title"
    :class="cn('leading-none', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </h4>
</template>
