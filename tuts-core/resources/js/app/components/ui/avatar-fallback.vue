<script setup lang="ts">
import { AvatarFallback as AvatarFallbackPrimitive } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{ class?: string }>()
</script>

<template>
  <AvatarFallbackPrimitive
    data-slot="avatar-fallback"
    :class="cn('bg-muted flex size-full items-center justify-center rounded-full', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </AvatarFallbackPrimitive>
</template>
