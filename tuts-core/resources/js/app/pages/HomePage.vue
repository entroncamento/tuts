<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ChevronRight } from '@lucide/vue'
import UCCard from '@/app/components/UCCard.vue'
import WeeklyCalendar from '@/app/components/WeeklyCalendar.vue'
import { fetchMySubjects } from '@/app/services/subjects'
import { UC_LIST, type UCData } from '@/app/data/ucData'
import { useAppRoleStore } from '@/app/stores/appRole'

const router = useRouter()
const roleStore = useAppRoleStore()

const ucs = ref<UCData[]>(UC_LIST)
const loading = ref(false)

const firstName = computed(() => {
  const name = roleStore.user?.name?.trim()
  if (!name) return 'Gil'
  return name.split(/\s+/)[0]
})

onMounted(async () => {
  loading.value = true
  try {
    ucs.value = await fetchMySubjects()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div style="height: 100%; overflow-y: auto; padding-bottom: 110px;">
    <div style="max-width: 1200px; margin: 0 auto; padding: 40px 24px;">

      <div style="margin-bottom: 40px;">
        <h1
          style="font-family: Inter, sans-serif; font-weight: 700; font-size: 32px; color: #1E1E1E; margin: 0; margin-bottom: 8px; line-height: 1.2;"
        >
          Bem-vindo, {{ firstName }}!
        </h1>
        <p
          style="font-family: Inter, sans-serif; font-weight: 400; font-size: 15px; color: #BDBABA; margin: 0; margin-bottom: 4px;"
        >
          Continua o teu estudo e consulta as unidades curriculares disponíveis.
        </p>
        <span
          style="font-family: Inter, sans-serif; font-weight: 400; font-size: 12px; color: #BDBABA;"
        >
          {{ roleStore.role === 'teacher' ? 'Modo docente' : 'Modo estudante' }}
        </span>
      </div>

      <section style="margin-bottom: 40px;">
        <div
          style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;"
        >
          <h2
            style="font-family: Inter, sans-serif; font-weight: 700; font-size: 20px; color: #1E1E1E; margin: 0;"
          >
            As tuas UC's
          </h2>
          <button
            class="flex items-center gap-1 transition-opacity hover:opacity-70"
            style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #009957; background: none; border: none; cursor: pointer; padding: 0; display: flex; align-items: center; gap: 4px;"
            @click="router.push({ name: 'ucs' })"
          >
            Ver todas
            <ChevronRight :size="14" :stroke-width="2" color="#009957" />
          </button>
        </div>

        <p v-if="loading" style="font-family: Inter, sans-serif; color: #9E9E9E;">
          A carregar UCs...
        </p>

        <p v-else-if="ucs.length === 0" style="font-family: Inter, sans-serif; color: #9E9E9E;">
          Ainda não existem UCs associadas à tua conta.
        </p>

        <div v-else style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; align-items: stretch;">
          <UCCard
            v-for="uc in ucs.slice(0, 6)"
            :key="uc.id"
            v-bind="uc"
          />
        </div>
      </section>

      <div style="height: 1px; background: #F0F0F0; margin-bottom: 40px;" />

      <section>
        <div
          style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;"
        >
          <h2
            style="font-family: Inter, sans-serif; font-weight: 700; font-size: 20px; color: #1E1E1E; margin: 0;"
          >
            O teu calendário
          </h2>
          <button
            class="flex items-center gap-1 transition-opacity hover:opacity-70"
            style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #009957; background: none; border: none; cursor: pointer; padding: 0; display: flex; align-items: center; gap: 4px;"
            @click="router.push({ name: 'calendar' })"
          >
            Ver calendário completo
            <ChevronRight :size="14" :stroke-width="2" color="#009957" />
          </button>
        </div>

        <WeeklyCalendar />
      </section>

      <div style="height: 32px;" />
    </div>
  </div>
</template>
