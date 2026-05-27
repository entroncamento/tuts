<script setup lang="ts">
// @radix-ui/react-label → radix-vue's Label primitive
// The Label primitive handles the htmlFor/for association and accessibility.
import { Label as LabelPrimitive } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{ class?: string }>()
</script>

<template>
  <LabelPrimitive
    data-slot="label"
    :class="cn(
      'flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50',
      props.class,
    )"
    v-bind="$attrs"
  >
    <slot />
  </LabelPrimitive>
</template>
