<script setup lang="ts">
import { SelectScrollUpButton } from 'radix-vue'
import { ChevronUp } from '@lucide/vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  class?: string
}>()
</script>

<template>
  <SelectScrollUpButton
    data-slot="select-scroll-up-button"
    :class="cn('flex cursor-default items-center justify-center py-1', props.class)"
    v-bind="$attrs"
  >
    <ChevronUp class="size-4" />
  </SelectScrollUpButton>
</template>
