<script setup lang="ts">
import { SelectItem, SelectItemText, SelectItemIndicator } from 'radix-vue'
import { Check } from '@lucide/vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  value: string
  class?: string
}>()
</script>

<template>
  <SelectItem
    data-slot="select-item"
    :value="props.value"
    :class="cn(
      'focus:bg-accent focus:text-accent-foreground [&_svg:not([class*=text-])]:text-muted-foreground relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-none select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=size-])]:size-4',
      props.class,
    )"
    v-bind="$attrs"
  >
    <span class="absolute right-2 flex size-3.5 items-center justify-center">
      <SelectItemIndicator>
        <Check class="size-4" />
      </SelectItemIndicator>
    </span>
    <SelectItemText>
      <slot />
    </SelectItemText>
  </SelectItem>
</template>
