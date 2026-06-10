<script setup lang="ts">
import { DropdownMenuSub } from 'radix-vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <DropdownMenuSub data-slot="dropdown-menu-sub" v-bind="$attrs">
    <slot />
  </DropdownMenuSub>
</template>
