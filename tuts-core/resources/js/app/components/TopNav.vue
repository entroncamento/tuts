<script setup lang="ts">
import { computed } from 'vue'
import { Bell, Search, Info } from '@lucide/vue'
import { useAppRoleStore } from '@/app/stores/appRole'

defineOptions({ name: 'AppTopNav' })

withDefaults(defineProps<{
  breadcrumb?: string
}>(), {
  breadcrumb: 'Homepage',
})

const roleStore = useAppRoleStore()

const avatar = computed(() => {
  const name = roleStore.user?.name ?? 'Utilizador'
  const parts = name.trim().split(/\s+/)
  const initials = parts.length >= 2
    ? `${parts[0][0]}${parts[parts.length - 1][0]}`
    : name.slice(0, 2)

  return {
    initials: initials.toUpperCase(),
    name: name.toUpperCase(),
    subtitle: roleStore.role === 'teacher' ? 'Docente' : 'Estudante',
    avatarBg: roleStore.role === 'teacher' ? '#1E3A8A' : '#009957',
    fontSize: initials.length > 1 ? 13 : 16,
  }
})
</script>

<template>
  <header
    class="fixed top-0 right-0 z-20 flex items-center"
    style="left: 80px; height: 72px; background: #ffffff; border-bottom: 1px solid #F0F0F0; padding-left: 24px; padding-right: 24px;"
  >
    <div class="flex items-center gap-2 flex-1">
      <span style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #BDBABA;">
        {{ breadcrumb }}
      </span>

      <span
        v-if="roleStore.role === 'teacher'"
        style="font-family: Inter, sans-serif; font-weight: 600; font-size: 10px; color: #1E3A8A; background: rgba(30,58,138,0.10); border-radius: 4px; padding: 2px 7px; letter-spacing: 0.04em; white-space: nowrap;"
      >
        MODO DOCENTE
      </span>
    </div>

    <div class="absolute left-1/2 -translate-x-1/2 flex items-center">
      <span style="font-family: Inter, sans-serif; font-weight: 700; font-size: 20px; color: #009957; letter-spacing: 0.08em;">
        TUT'S
      </span>
    </div>

    <div class="flex items-center gap-5 flex-1 justify-end">
      <button
        aria-label="Pesquisar"
        class="flex items-center justify-center transition-opacity hover:opacity-60"
        style="background: none; border: none; cursor: pointer; padding: 4px;"
      >
        <Search :size="18" :stroke-width="1.8" color="#1E1E1E" />
      </button>

      <div class="flex items-center gap-2">
        <Info :size="14" :stroke-width="1.8" color="#1E1E1E" />
        <span style="font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #1E1E1E; letter-spacing: 0.02em;">
          Responsible AI
        </span>
        <span style="width: 40px; height: 22px; border-radius: 11px; background: #009957; display: inline-flex; align-items: center; justify-content: flex-end; padding-right: 3px;">
          <span style="width: 16px; height: 16px; border-radius: 50%; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.2);" />
        </span>
      </div>

      <button
        aria-label="Notificações"
        class="flex items-center justify-center transition-opacity hover:opacity-60"
        style="background: none; border: none; cursor: pointer; padding: 4px; position: relative;"
      >
        <Bell :size="20" :stroke-width="1.8" color="#1E1E1E" />
        <span
          style="position: absolute; top: 2px; right: 2px; width: 8px; height: 8px; border-radius: 50%; background: #009957; border: 2px solid #ffffff;"
        />
      </button>

      <div style="width: 1px; height: 28px; background: #E5E5E5;" />

      <div class="flex items-center gap-3 rounded-lg" style="padding: 6px 8px 6px 6px; text-align: left;">
        <div
          class="flex items-center justify-center rounded-full flex-shrink-0"
          :style="{ width: '38px', height: '38px', background: avatar.avatarBg }"
        >
          <span
            :style="{ fontFamily: 'Inter, sans-serif', fontWeight: 700, fontSize: `${avatar.fontSize}px`, color: '#ffffff' }"
          >
            {{ avatar.initials }}
          </span>
        </div>

        <div class="hidden md:flex flex-col">
          <span style="font-family: Inter, sans-serif; font-weight: 700; font-size: 12px; color: #1E1E1E; line-height: 1.2;">
            {{ avatar.name }}
          </span>
          <span style="font-family: Inter, sans-serif; font-weight: 400; font-size: 11px; color: #9E9E9E;">
            {{ avatar.subtitle }}
          </span>
        </div>
      </div>
    </div>
  </header>
</template>
