<script setup lang="ts">
import { DialogOverlay } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  class?: string
}>()
</script>

<template>
  <DialogOverlay
    data-slot="dialog-overlay"
    :class="cn(
      'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/50',
      props.class,
    )"
    v-bind="$attrs"
  />
</template>
