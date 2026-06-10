<script setup lang="ts">
import { SelectRoot } from 'radix-vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <SelectRoot data-slot="select" v-bind="$attrs">
    <slot />
  </SelectRoot>
</template>
