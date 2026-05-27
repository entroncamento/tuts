<script setup lang="ts">
import { TabsList } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  class?: string
}>()
</script>

<template>
  <TabsList
    data-slot="tabs-list"
    :class="cn(
      'bg-muted text-muted-foreground inline-flex h-9 w-fit items-center justify-center rounded-lg p-[3px]',
      props.class,
    )"
    v-bind="$attrs"
  >
    <slot />
  </TabsList>
</template>
