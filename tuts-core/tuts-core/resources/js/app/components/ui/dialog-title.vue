<script setup lang="ts">
import { DialogTitle } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  class?: string
}>()
</script>

<template>
  <DialogTitle
    data-slot="dialog-title"
    :class="cn('text-lg font-semibold leading-none', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </DialogTitle>
</template>
