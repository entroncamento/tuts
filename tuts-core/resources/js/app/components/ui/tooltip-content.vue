<script setup lang="ts">
import { TooltipPortal, TooltipContent as TooltipContentPrimitive, TooltipArrow } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  sideOffset?: number
  class?:      string
}>(), {
  sideOffset: 0,
})
</script>

<template>
  <TooltipPortal>
    <TooltipContentPrimitive
      data-slot="tooltip-content"
      :side-offset="props.sideOffset"
      :class="cn(
        'bg-primary text-primary-foreground animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 w-fit origin-(--radix-tooltip-content-transform-origin) rounded-md px-3 py-1.5 text-xs text-balance',
        props.class,
      )"
      v-bind="$attrs"
    >
      <slot />
      <TooltipArrow class="bg-primary fill-primary z-50 size-2.5 translate-y-[calc(-50%_-_2px)] rotate-45 rounded-[2px]" />
    </TooltipContentPrimitive>
  </TooltipPortal>
</template>
