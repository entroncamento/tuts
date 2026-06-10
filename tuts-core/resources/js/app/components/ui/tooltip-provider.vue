<script setup lang="ts">
// @radix-ui/react-tooltip Provider → radix-vue TooltipProvider
import { TooltipProvider } from 'radix-vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  delayDuration?: number
}>(), {
  delayDuration: 0,
})
</script>

<template>
  <TooltipProvider
    data-slot="tooltip-provider"
    :delay-duration="props.delayDuration"
    v-bind="$attrs"
  >
    <slot />
  </TooltipProvider>
</template>
