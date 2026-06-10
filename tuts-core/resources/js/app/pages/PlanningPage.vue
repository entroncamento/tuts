<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Send, Check, CalendarDays, BookmarkPlus, X } from '@lucide/vue'
import WidgetCard from '@/app/components/WidgetCard.vue'
import { usePlanning } from '@/app/composables/usePlanning'
import { useShellHandler } from '@/app/composables/useShellHandler'
import type { Plan, ChatMessage, Goal } from '@/app/types'

// ─── Constants ────────────────────────────────────────────────────────────────
const AI_RESPONSES = [
  'Com base no teu horário, sugiro reservar segunda e terça de manhã para estudar Redes de Computadores. Tens disponibilidade nesses blocos!',
  'Para organizar a semana, começa pelas matérias mais exigentes nas manhãs. Vejo que tens tempo livre na terça e quinta de tarde.',
  'Recomendo 2 a 3 objetivos claros por semana. O que precisas consolidar antes do exame de Redes na sexta?',
  'Analisei o teu calendário — tens 4 blocos livres esta semana. Vamos distribuí-los pelas UCs com mais urgência.',
  'Para o exame de sexta-feira sugiro 2h de revisão na quarta e 1h de exercícios práticos na quinta à tarde.',
  'Boa estratégia! Dividir o estudo em blocos de 45 min com pausas de 10 min (Pomodoro) melhora a retenção em média 30%.',
]

const CHIPS = [
  { id: 'c1', label: 'estudar @disciplina' },
  { id: 'c2', label: 'organizar semana' },
  { id: 'c3', label: 'definir objetivos' },
]

type BlockType = 'busy' | 'study' | 'free'
const DAYS         = ['SEG', 'TER', 'QUA', 'QUI', 'SEX']
const BLOCK_LABELS = ['Manhã', 'Tarde', 'Noite']

const SCHEDULE: BlockType[][] = [
  ['study', 'busy',  'free'],
  ['free',  'study', 'study'],
  ['busy',  'free',  'study'],
  ['study', 'study', 'busy'],
  ['busy',  'busy',  'free'],
]

const BLOCK_COLORS: Record<BlockType, string> = {
  busy:  'rgba(229,57,53,0.14)',
  study: 'rgba(0,153,87,0.14)',
  free:  '#F5F5F5',
}
const BLOCK_BORDER: Record<BlockType, string> = {
  busy:  'rgba(229,57,53,0.22)',
  study: 'rgba(0,153,87,0.22)',
  free:  '#EBEBEB',
}

const DEADLINES = [
  { id: 'd1', text: 'Exame Redes de Computadores', date: 'Sex, 2 Mai',  time: '09:00', dot: '#E53935' },
  { id: 'd2', text: 'Entrega Projeto Final',        date: 'Qua, 30 Abr', time: '23:59', dot: '#F59E0B' },
  { id: 'd3', text: 'Ficha prática TACS',           date: 'Seg, 28 Abr', time: '18:00', dot: '#F59E0B' },
]

const DEFAULT_MESSAGES: ChatMessage[] = [
  {
    id:   'init-1',
    type: 'incoming',
    text: 'Olá Maria! 👋 Como posso ajudar a organizar a tua semana? Reparei que tens Exame de Redes na sexta-feira.',
    time: '10:00',
  },
]

const DEFAULT_GOALS: Goal[] = [
  { id: 'g1', text: 'Estudar Algoritmo de Dijkstra',    completed: true  },
  { id: 'g2', text: 'Completar ficha prática de Redes', completed: false },
  { id: 'g3', text: 'Rever Módulo 2 — Grafos',          completed: false },
  { id: 'g4', text: 'Fazer exercícios de MD (cap. 5)',  completed: false },
]

// ─── Helpers ──────────────────────────────────────────────────────────────────
function nowTime() {
  const d = new Date()
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}
function randomAI() {
  return AI_RESPONSES[Math.floor(Math.random() * AI_RESPONSES.length)]
}
function fmtDate(d: Date) {
  return d.toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit' })
}

// ─── Composables ─────────────────────────────────────────────────────────────
const route  = useRoute()
const router = useRouter()
const { plans, addPlan } = usePlanning()

// ─── State ────────────────────────────────────────────────────────────────────
const messages    = ref<ChatMessage[]>([...DEFAULT_MESSAGES])
const inputValue  = ref('')
const isTyping    = ref(false)
const goals       = ref<Goal[]>([...DEFAULT_GOALS])
const chatEnd     = ref<HTMLDivElement | null>(null)
const hoveredChip = ref<string | null>(null)

// Save button state
const saveState       = ref('Guardar em Os meus planos')
const saveGreen       = ref(false)
const isSaveModalOpen = ref(false)
const planName        = ref('')
const planSubject     = ref('')

// ─── Derived ─────────────────────────────────────────────────────────────────
const isSavedPlan = computed(() => !!route.params.id)

// ─── Load plan from route param ───────────────────────────────────────────────
watch(
  () => route.params.id,
  (newId) => {
    const id = newId as string | undefined
    if (id) {
      const existing = plans.value.find((p) => p.id === id)
      if (existing) {
        messages.value = existing.messages.length > 0 ? [...existing.messages] : [...DEFAULT_MESSAGES]
        goals.value    = existing.goals.length > 0    ? [...existing.goals]    : [...DEFAULT_GOALS]
        return
      }
    }
    messages.value = [...DEFAULT_MESSAGES]
    goals.value    = [...DEFAULT_GOALS]
  },
  { immediate: true },
)

// ─── Send message ─────────────────────────────────────────────────────────────
function sendMessage(text: string) {
  const trimmed = text.trim()
  if (!trimmed) return

  messages.value.push({ id: `msg-${Date.now()}`, type: 'outgoing', text: trimmed, time: nowTime() })
  inputValue.value = ''
  isTyping.value   = true
  nextTick(() => chatEnd.value?.scrollIntoView({ behavior: 'smooth' }))

  setTimeout(() => {
    isTyping.value = false
    messages.value.push({ id: `msg-ai-${Date.now()}`, type: 'incoming', text: randomAI(), time: nowTime() })
    nextTick(() => chatEnd.value?.scrollIntoView({ behavior: 'smooth' }))
  }, 1500)
}

function handleKeyDown(e: KeyboardEvent) {
  if (e.key === 'Enter') sendMessage(inputValue.value)
}

// ─── Goals ────────────────────────────────────────────────────────────────────
function toggleGoal(gId: string) {
  goals.value = goals.value.map((g) => g.id === gId ? { ...g, completed: !g.completed } : g)
}

// ─── Save plan ────────────────────────────────────────────────────────────────
function handleSave() {
  isSaveModalOpen.value = true
}

function handleModalSave() {
  if (!planName.value.trim()) return

  const outCount       = messages.value.filter((m) => m.type === 'outgoing').length
  const completedCount = goals.value.filter((g) => g.completed).length
  const today          = new Date()
  const endDate        = new Date(today.getTime() + 5 * 24 * 60 * 60 * 1000)

  const newPlan: Plan = {
    id:        `p-${Date.now()}`,
    title:     planName.value.trim(),
    subject:   planSubject.value || 'Sem disciplina',
    aiSummary:
      `${outCount} mensagem${outCount !== 1 ? 's' : ''} trocada${outCount !== 1 ? 's' : ''} com o assistente. ` +
      `${completedCount} de ${goals.value.length} metas concluídas. ` +
      `Plano criado para ${planSubject.value || 'estudo geral'}.`,
    duration:  `Ocupa ${Math.max(2, Math.ceil(goals.value.length * 1.2))} dias`,
    dateRange: `de ${fmtDate(today)} a ${fmtDate(endDate)}`,
    messages:  [...messages.value],
    goals:     [...goals.value],
  }

  addPlan(newPlan)
  isSaveModalOpen.value = false
  planName.value    = ''
  planSubject.value = ''
  saveState.value   = 'Guardado! ✓'
  saveGreen.value   = true

  setTimeout(() => {
    saveState.value = 'Guardar em Os meus planos'
    saveGreen.value = false
    router.push({ name: 'my-plans' })
  }, 1200)
}

// ─── Shell integration ────────────────────────────────────────────────────────
function handleShellSend(text: string) { sendMessage(text) }
useShellHandler(handleShellSend)
</script>

<template>
  <!-- ── Main two-column layout ── -->
  <div
    style="height: 100%; display: flex; padding: 24px; gap: 32px; overflow: hidden; box-sizing: border-box;"
  >

    <!-- ══════════════════════════════════════════════════════════════════
         LEFT COLUMN — Planning Chat
    ══════════════════════════════════════════════════════════════════ -->
    <div
      style="flex: 1; display: flex; flex-direction: column; height: 100%; overflow: hidden; min-width: 0;"
    >
      <!-- Header -->
      <h1
        style="font-family: Inter, sans-serif; font-weight: 700; font-size: 20px; color: #1E1E1E; margin: 0; margin-bottom: 16px; flex-shrink: 0;"
      >
        {{ isSavedPlan ? 'Plano Guardado' : 'Assistente de Planificação' }}
      </h1>

      <!-- Suggestion chips -->
      <div
        style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; flex-shrink: 0;"
      >
        <button
          v-for="chip in CHIPS"
          :key="chip.id"
          :style="{
            fontFamily: 'Inter, sans-serif',
            fontWeight: 400,
            fontSize: '13px',
            color: hoveredChip === chip.id ? '#009957' : '#1E1E1E',
            background: hoveredChip === chip.id ? '#EDF9EF' : '#F5F5F5',
            border: 'none',
            borderRadius: '9999px',
            padding: '8px 16px',
            cursor: 'pointer',
            outline: 'none',
            transition: 'background 0.15s ease, color 0.15s ease',
            whiteSpace: 'nowrap',
          }"
          @mouseenter="hoveredChip = chip.id"
          @mouseleave="hoveredChip = null"
          @click="sendMessage(chip.label)"
        >
          {{ chip.label }}
        </button>
      </div>

      <!-- Chat scroll area -->
      <div
        style="flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 12px;"
      >
        <div
          v-for="msg in messages"
          :key="msg.id"
          :style="{
            display: 'flex',
            flexDirection: 'column',
            alignItems: msg.type === 'outgoing' ? 'flex-end' : 'flex-start',
          }"
        >
          <div
            :style="{
              maxWidth: '78%',
              background: msg.type === 'outgoing' ? '#1E1E1E' : '#F5F5F5',
              borderRadius: msg.type === 'outgoing' ? '16px 16px 4px 16px' : '16px 16px 16px 4px',
              padding: '12px 16px',
            }"
          >
            <p
              :style="{
                fontFamily: 'Inter, sans-serif',
                fontWeight: 400,
                fontSize: '13px',
                color: msg.type === 'outgoing' ? '#ffffff' : '#1E1E1E',
                margin: 0,
                lineHeight: 1.65,
                whiteSpace: 'pre-line',
              }"
            >
              {{ msg.text }}
            </p>
          </div>
          <span
            :style="{
              fontFamily: 'Inter, sans-serif',
              fontWeight: 400,
              fontSize: '10px',
              color: '#C0C0C0',
              marginTop: '4px',
              paddingLeft:  msg.type === 'incoming' ? '4px' : '0',
              paddingRight: msg.type === 'outgoing' ? '4px' : '0',
            }"
          >
            {{ msg.time }}
          </span>
        </div>

        <!-- Typing indicator -->
        <div v-if="isTyping" style="display: flex; align-items: flex-start;">
          <div
            style="background: #F5F5F5; border-radius: 16px 16px 16px 4px; padding: 12px 18px; display: flex; align-items: center; gap: 5px;"
          >
            <div
              v-for="i in [0, 1, 2]"
              :key="i"
              :style="{
                width: '6px',
                height: '6px',
                borderRadius: '50%',
                background: '#BDBABA',
                animation: 'bounce 1.2s infinite',
                animationDelay: `${i * 0.2}s`,
              }"
            />
          </div>
        </div>

        <div ref="chatEnd" />
      </div>

      <!-- Pinned input -->
      <div
        style="flex-shrink: 0; margin-top: 16px; display: flex; align-items: center; height: 48px; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 9999px; padding-left: 16px; padding-right: 4px; gap: 8px;"
      >
        <input
          v-model="inputValue"
          type="text"
          placeholder="Escreve a tua questão..."
          style="flex: 1; font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #1E1E1E; background: none; border: none; outline: none;"
          @keydown="handleKeyDown"
        />
        <button
          style="width: 36px; height: 36px; border-radius: 50%; background: #009957; border: none; cursor: pointer; outline: none; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin: 4px;"
          @click="sendMessage(inputValue)"
        >
          <Send :size="15" :stroke-width="2" color="#ffffff" />
        </button>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         RIGHT COLUMN — Visual Dashboard
    ══════════════════════════════════════════════════════════════════ -->
    <div
      style="flex: 1; display: flex; flex-direction: column; height: 100%; overflow-y: auto; gap: 24px; padding-bottom: 24px; min-width: 0;"
    >
      <!-- Top actions -->
      <div style="display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;">
        <!-- Save button -->
        <button
          :disabled="isSavedPlan"
          :style="{
            fontFamily: 'Inter, sans-serif',
            fontWeight: 500,
            fontSize: '13px',
            color: (isSavedPlan || saveGreen) ? '#009957' : '#656966',
            background: 'none',
            border: `1px solid ${(isSavedPlan || saveGreen) ? 'rgba(0,153,87,0.35)' : '#E5E5E5'}`,
            borderRadius: '10px',
            padding: '9px 16px',
            cursor: isSavedPlan ? 'default' : 'pointer',
            outline: 'none',
            display: 'flex',
            alignItems: 'center',
            gap: '8px',
            transition: 'color 0.2s ease, border-color 0.2s ease',
          }"
          @click="!isSavedPlan && handleSave()"
        >
          <BookmarkPlus
            :size="14"
            :stroke-width="2"
            :color="(isSavedPlan || saveGreen) ? '#009957' : '#9E9E9E'"
          />
          {{ isSavedPlan ? 'Plano Guardado ✓' : saveState }}
        </button>

        <!-- Calendar button -->
        <button
          style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 10px; padding: 9px 16px; cursor: pointer; outline: none; display: flex; align-items: center; gap: 8px;"
          @click="router.push({ name: 'calendar' })"
        >
          <CalendarDays :size="14" :stroke-width="2" color="#9E9E9E" />
          Abrir calendário
        </button>
      </div>

      <!-- Widget: Disponibilidade da semana -->
      <div
        style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 16px; flex-shrink: 0;"
      >
        <!-- Widget header -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
          <span
            style="font-family: Inter, sans-serif; font-weight: 600; font-size: 13px; color: #1E1E1E;"
          >
            Disponibilidade da semana
          </span>
          <!-- Legend -->
          <div style="display: flex; gap: 12px;">
            <div
              v-for="leg in [
                { label: 'Ocupado', color: BLOCK_COLORS.busy  },
                { label: 'Estudo',  color: BLOCK_COLORS.study },
                { label: 'Livre',   color: BLOCK_COLORS.free  },
              ]"
              :key="leg.label"
              style="display: flex; align-items: center; gap: 5px;"
            >
              <div
                :style="{
                  width: '10px',
                  height: '10px',
                  borderRadius: '3px',
                  background: leg.color,
                  border: `1px solid ${leg.color === BLOCK_COLORS.free ? '#EBEBEB' : 'transparent'}`,
                }"
              />
              <span
                style="font-family: Inter, sans-serif; font-weight: 400; font-size: 10px; color: #9E9E9E;"
              >
                {{ leg.label }}
              </span>
            </div>
          </div>
        </div>

        <!-- Day columns -->
        <div style="display: flex; gap: 8px;">
          <div
            v-for="(day, di) in DAYS"
            :key="day"
            style="flex: 1; display: flex; flex-direction: column; gap: 4px;"
          >
            <span
              style="font-family: Inter, sans-serif; font-weight: 500; font-size: 10px; color: #9E9E9E; text-align: center; display: block; margin-bottom: 4px;"
            >
              {{ day }}
            </span>
            <div
              v-for="(blockType, bi) in SCHEDULE[di]"
              :key="bi"
              :title="`${BLOCK_LABELS[bi]}: ${blockType}`"
              :style="{
                height: '28px',
                borderRadius: '6px',
                background: BLOCK_COLORS[blockType],
                border: `1px solid ${BLOCK_BORDER[blockType]}`,
                width: '100%',
              }"
            />
            <span
              v-for="bl in BLOCK_LABELS"
              :key="bl"
              style="font-family: Inter, sans-serif; font-weight: 400; font-size: 8px; color: #C5C5C5; text-align: center; display: block; line-height: 1;"
            >
              {{ bl }}
            </span>
          </div>
        </div>
      </div>

      <!-- Widget: Próximos Eventos -->
      <WidgetCard title="Próximos Eventos" action-label="Ver todos" @action="router.push({ name: 'calendar' })">
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <div
            v-for="ev in DEADLINES"
            :key="ev.id"
            class="transition-colors hover:bg-[var(--color-surface-muted)]"
            style="display: flex; align-items: center; gap: 10px; padding: 6px 8px; border-radius: 8px; cursor: pointer;"
          >
            <div
              :style="{
                width: '8px',
                height: '8px',
                borderRadius: '50%',
                background: ev.dot,
                flexShrink: 0,
              }"
            />
            <div style="flex: 1; min-width: 0;">
              <p
                style="font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
              >
                {{ ev.text }}
              </p>
              <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 10px; color: #9E9E9E; margin: 0;">
                {{ ev.date }} · {{ ev.time }}
              </p>
            </div>
          </div>
        </div>
      </WidgetCard>

      <!-- Widget: Metas de Estudo -->
      <WidgetCard title="Metas de Estudo" action-label="+ Meta">
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <div
            v-for="goal in goals"
            :key="goal.id"
            class="transition-colors hover:bg-[var(--color-surface-muted)]"
            style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 4px 8px; border-radius: 8px;"
            @click="toggleGoal(goal.id)"
          >
            <!-- Square checkbox -->
            <div
              :style="{
                width: '18px',
                height: '18px',
                borderRadius: '5px',
                border: goal.completed ? 'none' : '1.5px solid #D0D0D0',
                background: goal.completed ? '#009957' : 'transparent',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flexShrink: 0,
                transition: 'background 0.15s ease',
              }"
            >
              <Check v-if="goal.completed" :size="11" :stroke-width="2.5" color="#ffffff" />
            </div>
            <span
              :style="{
                fontFamily: 'Inter, sans-serif',
                fontWeight: 400,
                fontSize: '13px',
                color: '#1E1E1E',
                textDecoration: goal.completed ? 'line-through' : 'none',
                opacity: goal.completed ? 0.45 : 1,
                transition: 'opacity 0.15s ease',
                flex: 1,
              }"
            >
              {{ goal.text }}
            </span>
          </div>
        </div>
      </WidgetCard>
    </div>
  </div>

  <!-- ── Save Plan Modal ── -->
  <Teleport to="body">
    <div
      v-if="isSaveModalOpen"
      class="fixed inset-0 z-[1000] flex items-center justify-center"
      style="background: rgba(0,0,0,0.4);"
      @click="isSaveModalOpen = false"
    >
      <div
        style="width: 400px; background: #ffffff; border-radius: 20px; padding: 24px; position: relative; display: flex; flex-direction: column; gap: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.18);"
        @click.stop
      >
        <!-- Close -->
        <button
          style="position: absolute; top: 16px; right: 16px; background: none; border: none; cursor: pointer; padding: 4px; outline: none; border-radius: 6px; display: flex; align-items: center; justify-content: center;"
          @click="isSaveModalOpen = false"
        >
          <X :size="16" :stroke-width="2" color="#9E9E9E" />
        </button>

        <h2
          style="font-family: Inter, sans-serif; font-weight: 700; font-size: 18px; color: #1E1E1E; margin: 0; padding-right: 28px;"
        >
          Guardar Plano de Estudo
        </h2>

        <!-- Plan name -->
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <label
            style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E;"
          >
            Nome do plano
          </label>
          <input
            v-model="planName"
            type="text"
            placeholder="Ex: Plano intensivo Redes..."
            autofocus
            style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #1E1E1E; border: 1px solid #E5E5E5; border-radius: 8px; padding: 10px 12px; outline: none; background: #FAFAFA; width: 100%; box-sizing: border-box;"
            @keydown.enter="planName.trim() && handleModalSave()"
          />
        </div>

        <!-- Disciplina (select) -->
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <label
            style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E;"
          >
            Disciplina
          </label>
          <select
            v-model="planSubject"
            :style="{
              fontFamily: 'Inter, sans-serif',
              fontWeight: 400,
              fontSize: '14px',
              color: planSubject ? '#1E1E1E' : '#9E9E9E',
              border: '1px solid #E5E5E5',
              borderRadius: '8px',
              padding: '10px 12px',
              outline: 'none',
              background: '#FAFAFA',
              width: '100%',
              cursor: 'pointer',
            }"
          >
            <option value="" disabled>Seleciona uma disciplina...</option>
            <option value="Redes de Computadores">Redes de Computadores</option>
            <option value="Matemática Discreta">Matemática Discreta</option>
          </select>
        </div>

        <!-- Actions -->
        <div style="display: flex; gap: 10px; justify-content: flex-end; padding-top: 4px;">
          <button
            style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 10px; padding: 10px 20px; cursor: pointer; outline: none;"
            @click="isSaveModalOpen = false"
          >
            Cancelar
          </button>
          <button
            :disabled="!planName.trim()"
            :style="{
              fontFamily: 'Inter, sans-serif',
              fontWeight: 600,
              fontSize: '14px',
              color: '#ffffff',
              background: planName.trim() ? '#009957' : '#BDBABA',
              border: 'none',
              borderRadius: '10px',
              padding: '10px 20px',
              cursor: planName.trim() ? 'pointer' : 'not-allowed',
              outline: 'none',
              transition: 'background 0.15s ease',
            }"
            @click="handleModalSave"
          >
            Guardar
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@keyframes bounce {
  0%, 80%, 100% { transform: translateY(0); }
  40%           { transform: translateY(-6px); }
}
</style>
