<script setup lang="ts">
import { SelectSeparator } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  class?: string
}>()
</script>

<template>
  <SelectSeparator
    data-slot="select-separator"
    :class="cn('bg-border pointer-events-none -mx-1 my-1 h-px', props.class)"
    v-bind="$attrs"
  />
</template>
