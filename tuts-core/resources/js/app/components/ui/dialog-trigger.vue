<script setup lang="ts">
import { DialogTrigger } from 'radix-vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <DialogTrigger data-slot="dialog-trigger" v-bind="$attrs">
    <slot />
  </DialogTrigger>
</template>
