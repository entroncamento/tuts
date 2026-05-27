<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { Calendar, Trash2, AlertTriangle, X, Plus, ChevronRight } from '@lucide/vue'
import { usePlanning } from '@/app/composables/usePlanning'
import type { Plan } from '@/app/types'

const router = useRouter()
const { plans, deletePlan } = usePlanning()

// ─── Delete confirmation ───────────────────────────────────────────────────────
const deletingPlanId = ref<string | null>(null)
const planToDelete   = computed<Plan | null>(() => plans.value.find((p) => p.id === deletingPlanId.value) ?? null)

function confirmDelete() {
  if (!deletingPlanId.value) return
  deletePlan(deletingPlanId.value)
  deletingPlanId.value = null
}

// ─── Per-card hover state ─────────────────────────────────────────────────────
const cardHovered  = ref<Record<string, boolean>>({})
const trashHovered = ref<Record<string, boolean>>({})

// ─── Duration/date toggle per plan ────────────────────────────────────────────
const showDates = ref<Record<string, boolean>>({})

function toggleDates(planId: string, e: Event) {
  e.stopPropagation()
  showDates.value = { ...showDates.value, [planId]: !showDates.value[planId] }
}
</script>

<template>
  <!-- ── Scrollable page ── -->
  <div style="height: 100%; overflow-y: auto; box-sizing: border-box; padding: 32px;">
    <div style="max-width: 1200px; margin: 0 auto;">

      <!-- ── Page header ── -->
      <div
        style="display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 32px;"
      >
        <div>
          <h1
            style="font-family: Inter, sans-serif; font-weight: 700; font-size: 24px; color: #1E1E1E; margin: 0; margin-bottom: 6px;"
          >
            Os Meus Planos
          </h1>
          <p
            style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #9E9E9E; margin: 0;"
          >
            Consulta e gere as tuas estratégias de estudo guardadas.
          </p>
        </div>

        <!-- CTA → new plan -->
        <button
          style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #ffffff; background: #009957; border: none; border-radius: 10px; padding: 10px 18px; cursor: pointer; outline: none; display: flex; align-items: center; gap: 8px;"
          @click="router.push({ name: 'planning' })"
        >
          <Plus :size="15" :stroke-width="2" color="#ffffff" />
          Novo plano
        </button>
      </div>

      <!-- ── Empty state ── -->
      <div
        v-if="plans.length === 0"
        style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; padding: 80px 0;"
      >
        <div
          style="width: 56px; height: 56px; border-radius: 16px; background: #F5F5F5; display: flex; align-items: center; justify-content: center;"
        >
          <Calendar :size="24" :stroke-width="1.5" color="#BDBABA" />
        </div>
        <div style="text-align: center;">
          <p
            style="font-family: Inter, sans-serif; font-weight: 600; font-size: 16px; color: #1E1E1E; margin: 0; margin-bottom: 6px;"
          >
            Ainda não tens planos guardados
          </p>
          <p
            style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #9E9E9E; margin: 0;"
          >
            Cria um novo plano no Assistente de Planificação.
          </p>
        </div>
        <button
          style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #009957; background: rgba(0,153,87,0.08); border: none; border-radius: 8px; padding: 10px 20px; cursor: pointer; outline: none;"
          @click="router.push({ name: 'planning' })"
        >
          Ir para o Assistente
        </button>
      </div>

      <!-- ── Plans grid ── -->
      <div
        v-else
        style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;"
      >
        <div
          v-for="plan in plans"
          :key="plan.id"
          :style="{
            background: '#ffffff',
            border: `1px solid ${cardHovered[plan.id] ? '#CCCCCC' : '#E5E5E5'}`,
            borderRadius: '12px',
            padding: '20px',
            position: 'relative',
            display: 'flex',
            flexDirection: 'column',
            cursor: 'pointer',
            transition: 'border-color 0.15s ease, box-shadow 0.15s ease',
            boxShadow: cardHovered[plan.id] ? '0 4px 16px rgba(0,0,0,0.07)' : 'none',
          }"
          @click="router.push({ name: 'planning-detail', params: { id: plan.id } })"
          @mouseenter="cardHovered[plan.id] = true"
          @mouseleave="cardHovered[plan.id] = false"
        >
          <!-- Open indicator: ChevronRight, appears on hover, shifts right when trash hovered -->
          <div
            :style="{
              position: 'absolute',
              top: '16px',
              right: trashHovered[plan.id] ? '48px' : '16px',
              opacity: cardHovered[plan.id] && !trashHovered[plan.id] ? 1 : 0,
              transition: 'opacity 0.15s ease, right 0.15s ease',
              pointerEvents: 'none',
            }"
          >
            <ChevronRight :size="16" :stroke-width="2" color="#009957" />
          </div>

          <!-- Delete button (must stop propagation) -->
          <button
            :style="{
              position: 'absolute',
              top: '16px',
              right: '16px',
              background: trashHovered[plan.id] ? '#FEF2F2' : 'transparent',
              border: 'none',
              borderRadius: '6px',
              padding: '6px',
              cursor: 'pointer',
              outline: 'none',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              transition: 'background 0.15s ease',
              zIndex: 1,
            }"
            @click.stop="deletingPlanId = plan.id"
            @mouseenter="trashHovered[plan.id] = true"
            @mouseleave="trashHovered[plan.id] = false"
          >
            <Trash2
              :size="15"
              :stroke-width="1.8"
              :color="trashHovered[plan.id] ? '#EF4444' : '#BDBABA'"
            />
          </button>

          <!-- Title -->
          <p
            style="font-family: Inter, sans-serif; font-weight: 600; font-size: 16px; color: #1E1E1E; margin: 0; max-width: 85%; line-height: 1.35;"
          >
            {{ plan.title }}
          </p>

          <!-- Subject tag -->
          <div style="margin-top: 8px;">
            <span
              style="display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 6px; background: rgba(0,153,87,0.12); color: #009957; font-size: 11px; font-weight: 600; font-family: Inter, sans-serif; white-space: nowrap;"
            >
              @{{ plan.subject }}
            </span>
          </div>

          <!-- AI Summary -->
          <p
            style="font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #656966; margin: 0; margin-top: 16px; line-height: 1.55;"
          >
            {{ plan.aiSummary }}
          </p>

          <!-- Footer: duration / date range toggle (must stop propagation) -->
          <div
            title="Clica para alternar entre duração e datas"
            style="margin-top: 16px; display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 2px 0; width: fit-content;"
            @click.stop="toggleDates(plan.id, $event)"
          >
            <Calendar :size="13" :stroke-width="1.8" color="#9E9E9E" />
            <span
              style="font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #9E9E9E; transition: color 0.15s ease; text-decoration: underline dotted; text-underline-offset: 3px;"
            >
              {{ showDates[plan.id] ? plan.dateRange : plan.duration }}
            </span>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ── Delete confirmation modal ── -->
  <Teleport to="body">
    <div
      v-if="deletingPlanId"
      class="fixed inset-0 z-[1000] flex items-center justify-center"
      style="background: rgba(0,0,0,0.4);"
      @click="deletingPlanId = null"
    >
      <div
        style="width: 420px; background: #ffffff; border-radius: 20px; padding: 28px; position: relative; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.18);"
        @click.stop
      >
        <!-- Close -->
        <button
          style="position: absolute; top: 16px; right: 16px; background: none; border: none; cursor: pointer; padding: 4px; outline: none; border-radius: 6px; display: flex; align-items: center; justify-content: center;"
          @click="deletingPlanId = null"
        >
          <X :size="16" :stroke-width="2" color="#9E9E9E" />
        </button>

        <!-- Red icon -->
        <div
          style="width: 44px; height: 44px; border-radius: 12px; background: rgba(229,57,53,0.10); display: flex; align-items: center; justify-content: center; margin-bottom: 16px;"
        >
          <AlertTriangle :size="22" :stroke-width="2" color="#E53935" />
        </div>

        <h2
          style="font-family: Inter, sans-serif; font-weight: 700; font-size: 18px; color: #1E1E1E; margin: 0; margin-bottom: 10px;"
        >
          Eliminar Plano?
        </h2>

        <!-- Plan title in grey bg -->
        <p
          v-if="planToDelete"
          style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E; margin: 0 0 8px; background: #F5F5F5; border-radius: 6px; padding: 6px 10px; display: inline-block;"
        >
          "{{ planToDelete.title }}"
        </p>

        <p
          style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #656966; margin: 8px 0 24px; line-height: 1.6;"
        >
          Tens a certeza? Se apagares este plano, vais apagar todos os
          eventos criados com ele no teu calendário. Esta ação é
          irreversível.
        </p>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
          <button
            style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 10px; padding: 10px 20px; cursor: pointer; outline: none;"
            @click="deletingPlanId = null"
          >
            Cancelar
          </button>
          <button
            style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #ffffff; background: #E53935; border: none; border-radius: 10px; padding: 10px 20px; cursor: pointer; outline: none;"
            @click="confirmDelete"
          >
            Eliminar Plano
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
