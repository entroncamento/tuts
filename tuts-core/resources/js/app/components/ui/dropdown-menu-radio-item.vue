<script setup lang="ts">
import { DropdownMenuRadioItem, DropdownMenuItemIndicator } from 'radix-vue'
import { Circle } from '@lucide/vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  value: string
  class?: string
}>()
</script>

<template>
  <DropdownMenuRadioItem
    data-slot="dropdown-menu-radio-item"
    :value="props.value"
    :class="cn(
      'focus:bg-accent focus:text-accent-foreground relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pr-2 pl-8 text-sm outline-none transition-colors select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=size-])]:size-4',
      props.class,
    )"
    v-bind="$attrs"
  >
    <span class="pointer-events-none absolute left-2 flex size-3.5 items-center justify-center">
      <DropdownMenuItemIndicator>
        <Circle class="size-2 fill-current" />
      </DropdownMenuItemIndicator>
    </span>
    <slot />
  </DropdownMenuRadioItem>
</template>
