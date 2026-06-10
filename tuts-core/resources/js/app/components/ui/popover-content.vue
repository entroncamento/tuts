<script setup lang="ts">
import { PopoverPortal, PopoverContent } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  align?:      'start' | 'center' | 'end'
  sideOffset?: number
  class?:      string
}>(), {
  align:      'center',
  sideOffset: 4,
})
</script>

<template>
  <PopoverPortal>
    <PopoverContent
      data-slot="popover-content"
      :align="props.align"
      :side-offset="props.sideOffset"
      :class="cn(
        'bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 w-72 origin-(--radix-popover-content-transform-origin) rounded-lg border p-4 shadow-md outline-none',
        props.class,
      )"
      v-bind="$attrs"
    >
      <slot />
    </PopoverContent>
  </PopoverPortal>
</template>
