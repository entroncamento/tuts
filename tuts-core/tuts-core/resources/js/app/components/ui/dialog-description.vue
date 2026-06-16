<script setup lang="ts">
import { DialogDescription } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  class?: string
}>()
</script>

<template>
  <DialogDescription
    data-slot="dialog-description"
    :class="cn('text-muted-foreground text-sm', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </DialogDescription>
</template>
