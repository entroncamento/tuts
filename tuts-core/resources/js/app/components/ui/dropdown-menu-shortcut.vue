<script setup lang="ts">
import { cn } from './utils'

const props = defineProps<{
  class?: string
}>()
</script>

<template>
  <span
    data-slot="dropdown-menu-shortcut"
    :class="cn('ml-auto text-xs tracking-widest text-muted-foreground', props.class)"
  >
    <slot />
  </span>
</template>
