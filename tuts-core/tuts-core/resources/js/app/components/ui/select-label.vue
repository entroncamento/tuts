<script setup lang="ts">
import { SelectLabel } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  class?: string
}>()
</script>

<template>
  <SelectLabel
    data-slot="select-label"
    :class="cn('px-2 py-1.5 text-xs font-medium text-muted-foreground', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </SelectLabel>
</template>
