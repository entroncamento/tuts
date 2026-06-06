<script setup lang="ts">
// @radix-ui/react-avatar Root → radix-vue AvatarRoot
import { AvatarRoot } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{ class?: string }>()
</script>

<template>
  <AvatarRoot
    data-slot="avatar"
    :class="cn('relative flex size-10 shrink-0 overflow-hidden rounded-full', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </AvatarRoot>
</template>
