<script setup lang="ts">
// @radix-ui/react-switch → radix-vue SwitchRoot + SwitchThumb
import { SwitchRoot, SwitchThumb } from 'radix-vue'
import { cn } from './utils'

defineOptions({ inheritAttrs: false })

const props = defineProps<{ class?: string }>()
</script>

<template>
  <SwitchRoot
    data-slot="switch"
    :class="cn(
      'peer data-[state=checked]:bg-primary data-[state=unchecked]:bg-switch-background focus-visible:border-ring focus-visible:ring-ring/50 dark:data-[state=unchecked]:bg-input/80 inline-flex h-[1.15rem] w-8 shrink-0 items-center rounded-full border border-transparent transition-all outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    )"
    v-bind="$attrs"
  >
    <SwitchThumb
      data-slot="switch-thumb"
      class="bg-card dark:data-[state=unchecked]:bg-card-foreground dark:data-[state=checked]:bg-primary-foreground pointer-events-none block size-4 rounded-full ring-0 transition-transform data-[state=checked]:translate-x-[calc(100%-2px)] data-[state=unchecked]:translate-x-0"
    />
  </SwitchRoot>
</template>
