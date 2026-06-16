<script setup lang="ts">
import { computed } from 'vue'
import { Lock, RefreshCcw } from '@lucide/vue'
import { useAppRoleStore } from '@/app/stores/appRole'
import type { AppRole } from '@/app/types'

defineOptions({ name: 'RoleGuard' })

// ─── Props ────────────────────────────────────────────────────────────────────
// required: the role that is allowed to see the slotted content.
const props = defineProps<{
  required: AppRole
}>()

// ─── Store ────────────────────────────────────────────────────────────────────
const roleStore = useAppRoleStore()

// ─── Access check ─────────────────────────────────────────────────────────────
const hasAccess = computed(() => roleStore.role === props.required)

// ─── Restricted screen config ─────────────────────────────────────────────────
const restricted = computed(() => {
  if (props.required === 'teacher') {
    return {
      message:     'Esta área é exclusiva para Docentes. Podes alternar o teu papel clicando no teu nome na barra de topo (TopNav).',
      switchLabel: 'Entrar como Docente',
      switchTo:    'teacher' as AppRole,
    }
  }
  return {
    message:     'Esta área é exclusiva para Estudantes. Podes alternar o teu papel clicando no teu nome na barra de topo (TopNav).',
    switchLabel: 'Entrar como Estudante',
    switchTo:    'student' as AppRole,
  }
})
</script>

<template>
  <!-- Allow access -->
  <slot v-if="hasAccess" />

  <!-- Restricted screen — mirrors React's RestrictedScreen component -->
  <div
    v-else
    style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%; margin-top: 80px; gap: 20px; color: #9E9E9E;"
  >
    <!-- Lock icon circle -->
    <div
      style="background: #F5F5F5; border-radius: 50%; padding: 24px; display: flex; align-items: center; justify-content: center;"
    >
      <Lock :size="40" :stroke-width="1.5" color="#BDBABA" />
    </div>

    <!-- Copy -->
    <div style="text-align: center; max-width: 320px;">
      <p
        style="font-family: Inter, sans-serif; font-weight: 700; font-size: 20px; color: #1E1E1E; margin: 0; margin-bottom: 8px;"
      >
        Acesso Restrito
      </p>
      <p
        style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #9E9E9E; margin: 0; line-height: 1.6;"
      >
        {{ restricted.message }}
      </p>
    </div>

    <!-- Quick role-switch button -->
    <button
      class="transition-opacity hover:opacity-80"
      style="display: flex; align-items: center; gap: 8px; font-family: Inter, sans-serif; font-weight: 600; font-size: 13px; color: #009957; background: #EDF9EF; border: 1px solid #009957; border-radius: 10px; padding: 10px 20px 10px 16px; cursor: pointer;"
      @click="roleStore.setRole(restricted.switchTo)"
    >
      <RefreshCcw :size="14" :stroke-width="2.2" />
      {{ restricted.switchLabel }}
    </button>

    <p
      style="font-family: Inter, sans-serif; font-weight: 400; font-size: 11px; color: #BDBABA; margin: 0; text-align: center;"
    >
      Ou alterna o teu papel clicando no teu nome na barra de topo.
    </p>
  </div>
</template>
