<script setup lang="ts">
import { SelectValue } from 'radix-vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <SelectValue data-slot="select-value" v-bind="$attrs">
    <slot />
  </SelectValue>
</template>
