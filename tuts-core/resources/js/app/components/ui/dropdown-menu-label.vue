<script setup lang="ts">
import { DropdownMenuLabel } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  inset?:  boolean
  class?:  string
}>(), {
  inset: false,
})
</script>

<template>
  <DropdownMenuLabel
    data-slot="dropdown-menu-label"
    :data-inset="props.inset || undefined"
    :class="cn(
      'px-2 py-1.5 text-xs font-medium text-muted-foreground data-[inset]:pl-8',
      props.class,
    )"
    v-bind="$attrs"
  >
    <slot />
  </DropdownMenuLabel>
</template>
