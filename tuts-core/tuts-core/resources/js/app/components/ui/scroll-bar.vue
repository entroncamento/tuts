<script setup lang="ts">
import { ScrollAreaScrollbar, ScrollAreaThumb } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  orientation?: 'vertical' | 'horizontal'
  class?:       string
}>(), {
  orientation: 'vertical',
})
</script>

<template>
  <ScrollAreaScrollbar
    data-slot="scroll-area-scrollbar"
    :orientation="props.orientation"
    :class="cn(
      'flex touch-none p-px transition-colors select-none',
      props.orientation === 'vertical'   && 'h-full w-2.5 border-l border-l-transparent',
      props.orientation === 'horizontal' && 'h-2.5 flex-col border-t border-t-transparent',
      props.class,
    )"
    v-bind="$attrs"
  >
    <ScrollAreaThumb
      data-slot="scroll-area-thumb"
      class="bg-border relative flex-1 rounded-full"
    />
  </ScrollAreaScrollbar>
</template>
