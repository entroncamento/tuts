<script setup lang="ts">
import { DropdownMenuSeparator } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  class?: string
}>()
</script>

<template>
  <DropdownMenuSeparator
    data-slot="dropdown-menu-separator"
    :class="cn('bg-muted -mx-1 my-1 h-px', props.class)"
    v-bind="$attrs"
  />
</template>
