<script setup lang="ts">
import { TabsRoot } from 'radix-vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <TabsRoot data-slot="tabs" v-bind="$attrs">
    <slot />
  </TabsRoot>
</template>
