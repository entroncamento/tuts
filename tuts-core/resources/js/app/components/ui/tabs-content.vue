<script setup lang="ts">
import { TabsContent } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  value: string
  class?: string
}>()
</script>

<template>
  <TabsContent
    data-slot="tabs-content"
    :value="props.value"
    :class="cn(
      'flex-1 outline-none',
      props.class,
    )"
    v-bind="$attrs"
  >
    <slot />
  </TabsContent>
</template>
