<script setup lang="ts">
import { DialogRoot } from 'radix-vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <DialogRoot data-slot="dialog" v-bind="$attrs">
    <slot />
  </DialogRoot>
</template>
