<script setup lang="ts">
// @radix-ui/react-separator → radix-vue Separator
// The Separator primitive manages aria-orientation and decorative attribute.
import { Separator as SeparatorPrimitive } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  orientation?: 'horizontal' | 'vertical'
  decorative?:  boolean
  class?:       string
}>(), {
  orientation: 'horizontal',
  decorative:  true,
})
</script>

<template>
  <SeparatorPrimitive
    data-slot="separator-root"
    :orientation="props.orientation"
    :decorative="props.decorative"
    :class="cn(
      'bg-border shrink-0 data-[orientation=horizontal]:h-px data-[orientation=horizontal]:w-full data-[orientation=vertical]:h-full data-[orientation=vertical]:w-px',
      props.class,
    )"
    v-bind="$attrs"
  />
</template>
