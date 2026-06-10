<script setup lang="ts">
import { DropdownMenuSubTrigger } from 'radix-vue'
import { ChevronRight } from '@lucide/vue'
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
  <DropdownMenuSubTrigger
    data-slot="dropdown-menu-sub-trigger"
    :data-inset="props.inset || undefined"
    :class="cn(
      'focus:bg-accent data-[state=open]:bg-accent flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none select-none data-[inset]:pl-8 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=size-])]:size-4',
      props.class,
    )"
    v-bind="$attrs"
  >
    <slot />
    <ChevronRight class="ml-auto size-4" />
  </DropdownMenuSubTrigger>
</template>
