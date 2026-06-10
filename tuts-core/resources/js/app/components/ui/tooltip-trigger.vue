<script setup lang="ts">
import { TooltipTrigger as TooltipTriggerPrimitive } from 'radix-vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <TooltipTriggerPrimitive data-slot="tooltip-trigger" v-bind="$attrs">
    <slot />
  </TooltipTriggerPrimitive>
</template>
