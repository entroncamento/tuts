<script setup lang="ts">
// @radix-ui/react-scroll-area → radix-vue ScrollAreaRoot + children
import {
  ScrollAreaRoot,
  ScrollAreaViewport,
  ScrollAreaScrollbar,
  ScrollAreaThumb,
  ScrollAreaCorner,
} from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{ class?: string }>()
</script>

<template>
  <ScrollAreaRoot
    data-slot="scroll-area"
    :class="cn('relative', props.class)"
    v-bind="$attrs"
  >
    <ScrollAreaViewport
      data-slot="scroll-area-viewport"
      class="focus-visible:ring-ring/50 size-full rounded-[inherit] transition-[color,box-shadow] outline-none focus-visible:ring-[3px] focus-visible:outline-1"
    >
      <slot />
    </ScrollAreaViewport>
    <!-- ScrollBar is rendered as a named slot so consumers can pass orientation -->
    <slot name="scrollbar">
      <ScrollAreaScrollbar
        data-slot="scroll-area-scrollbar"
        orientation="vertical"
        class="flex touch-none p-px transition-colors select-none h-full w-2.5 border-l border-l-transparent"
      >
        <ScrollAreaThumb
          data-slot="scroll-area-thumb"
          class="bg-border relative flex-1 rounded-full"
        />
      </ScrollAreaScrollbar>
    </slot>
    <ScrollAreaCorner />
  </ScrollAreaRoot>
</template>
