<script setup lang="ts">
import { DropdownMenuRadioGroup } from 'radix-vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <DropdownMenuRadioGroup data-slot="dropdown-menu-radio-group" v-bind="$attrs">
    <slot />
  </DropdownMenuRadioGroup>
</template>
