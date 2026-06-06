<script setup lang="ts">
import { DropdownMenuPortal } from 'radix-vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <DropdownMenuPortal data-slot="dropdown-menu-portal" v-bind="$attrs">
    <slot />
  </DropdownMenuPortal>
</template>
