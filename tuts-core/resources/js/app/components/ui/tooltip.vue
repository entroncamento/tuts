<script setup lang="ts">
// Tooltip wraps TooltipProvider + TooltipRoot so consumers get one import.
// Mirrors the React Tooltip component that internally renders <TooltipProvider>.
import { TooltipProvider, TooltipRoot } from 'radix-vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <TooltipProvider :delay-duration="0">
    <TooltipRoot data-slot="tooltip" v-bind="$attrs">
      <slot />
    </TooltipRoot>
  </TooltipProvider>
</template>
