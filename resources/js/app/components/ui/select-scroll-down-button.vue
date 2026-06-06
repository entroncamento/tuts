<script setup lang="ts">
import { SelectScrollDownButton } from 'radix-vue'
import { ChevronDown } from '@lucide/vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  class?: string
}>()
</script>

<template>
  <SelectScrollDownButton
    data-slot="select-scroll-down-button"
    :class="cn('flex cursor-default items-center justify-center py-1', props.class)"
    v-bind="$attrs"
  >
    <ChevronDown class="size-4" />
  </SelectScrollDownButton>
</template>
