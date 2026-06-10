<script setup lang="ts">
// @radix-ui/react-progress → radix-vue ProgressRoot + ProgressIndicator
import { ProgressRoot, ProgressIndicator } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  value?: number
  class?: string
}>()
</script>

<template>
  <ProgressRoot
    data-slot="progress"
    :value="props.value"
    :class="cn('bg-primary/20 relative h-2 w-full overflow-hidden rounded-full', props.class)"
    v-bind="$attrs"
  >
    <ProgressIndicator
      data-slot="progress-indicator"
      class="bg-primary h-full w-full flex-1 transition-all"
      :style="{ transform: `translateX(-${100 - (props.value || 0)}%)` }"
    />
  </ProgressRoot>
</template>
