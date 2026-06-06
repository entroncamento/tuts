<script setup lang="ts">
import { PopoverAnchor } from 'radix-vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <PopoverAnchor data-slot="popover-anchor" v-bind="$attrs">
    <slot />
  </PopoverAnchor>
</template>
