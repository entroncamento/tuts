<script setup lang="ts">
import { AvatarImage as AvatarImagePrimitive } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{ class?: string }>()
</script>

<template>
  <AvatarImagePrimitive
    data-slot="avatar-image"
    :class="cn('aspect-square size-full', props.class)"
    v-bind="$attrs"
  />
</template>
