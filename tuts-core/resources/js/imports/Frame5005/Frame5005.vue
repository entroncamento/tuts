<template>
  <div class="bg-[#323232] relative size-full">
    <HomepageFrame />
    <p class="absolute font-['Inter:Regular',sans-serif] font-normal leading-[normal] left-[851px] not-italic text-[96px] text-white top-[97px] whitespace-nowrap">homepage</p>
    <p class="absolute font-['Inter:Regular',sans-serif] font-normal leading-[normal] left-[2722px] not-italic text-[96px] text-white top-[97px] whitespace-nowrap">chat (sem estar anexado)</p>
    <p class="absolute font-['Inter:Regular',sans-serif] font-normal leading-[normal] left-[3002px] not-italic text-[96px] text-white top-[1482px] whitespace-nowrap">calendario</p>
    <p class="absolute font-['Inter:Regular',sans-serif] font-normal leading-[normal] left-[785px] not-italic text-[96px] text-white top-[1432px] whitespace-nowrap">chat anexado</p>
    <ChatComeceFrame />
    <ChatAnexadoFrame />
    <CalendarioFrame />
  </div>
</template>

<script setup lang="ts">
import HomepageFrame from './HomepageFrame.vue'
import ChatComeceFrame from './ChatComeceFrame.vue'
import ChatAnexadoFrame from './ChatAnexadoFrame.vue'
import CalendarioFrame from './CalendarioFrame.vue'

defineOptions({ name: 'Frame5005' })
</script>
