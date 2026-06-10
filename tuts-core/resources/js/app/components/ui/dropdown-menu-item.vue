<script setup lang="ts">
import { DropdownMenuItem } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  inset?:  boolean
  variant?: 'default' | 'destructive'
  class?:  string
}>(), {
  inset:   false,
  variant: 'default',
})
</script>

<template>
  <DropdownMenuItem
    data-slot="dropdown-menu-item"
    :data-inset="props.inset || undefined"
    :data-variant="props.variant"
    :class="cn(
      'focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&_svg:not([class*=text-])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none transition-colors select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=size-])]:size-4',
      props.class,
    )"
    v-bind="$attrs"
  >
    <slot />
  </DropdownMenuItem>
</template>
