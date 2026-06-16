<script setup lang="ts">
import { ref, computed } from 'vue'
import {
  Plus, X, ChevronLeft, ChevronRight,
  SlidersHorizontal, Check, Sparkles, Bot,
} from '@lucide/vue'
import WidgetCard from '@/app/components/WidgetCard.vue'
import MiniCalendar from '@/app/components/MiniCalendar.vue'
import { useOutsideClick } from '@/app/composables/useOutsideClick'

// ─── Types ────────────────────────────────────────────────────────────────────
type ViewType  = 'Dia' | 'Semana' | 'Mês' | 'Ano'
type EventType = 'study' | 'exam' | 'neutral'

interface CalEvent {
  id:        string
  title:     string
  subtitle?: string
  day:       number
  startHour: number
  startMin:  number
  endHour:   number
  endMin:    number
  type:      EventType
  isAI?:     boolean
}

interface Reminder {
  id:       string
  text:     string
  tag:      string
  tagColor: string
}

// ─── Constants ────────────────────────────────────────────────────────────────
const HOUR_HEIGHT       = 64
const START_HOUR        = 8
const END_HOUR          = 20
const HOURS             = Array.from({ length: END_HOUR - START_HOUR + 1 }, (_, i) => i + START_HOUR)
const TIME_COL_W        = 52
const TOTAL_GRID_HEIGHT = (END_HOUR - START_HOUR) * HOUR_HEIGHT

const WEEK_DAYS = [
  { label: 'SEG', date: 20, dayIdx: 0 },
  { label: 'TER', date: 21, dayIdx: 1 },
  { label: 'QUA', date: 22, dayIdx: 2 },
  { label: 'QUI', date: 23, dayIdx: 3 },
  { label: 'SEX', date: 24, dayIdx: 4 },
]

const TODAY_COL = [{ label: 'SEX', date: 24, dayIdx: 4 }]

const INITIAL_EVENTS: CalEvent[] = [
  { id: 'e1', title: 'Algoritmia e Prog.',   subtitle: 'Sala A101',         day: 0, startHour: 9,  startMin: 0,  endHour: 10, endMin: 30, type: 'study'   },
  { id: 'e2', title: 'Matemática Discreta',  subtitle: 'Sala B203',         day: 1, startHour: 10, startMin: 0,  endHour: 12, endMin: 0,  type: 'study'   },
  { id: 'e3', title: 'Estudo Cap. 4',                                        day: 2, startHour: 10, startMin: 0,  endHour: 11, endMin: 30, type: 'neutral' },
  { id: 'e4', title: 'Bases de Dados',       subtitle: 'Lab. Informática',  day: 2, startHour: 14, startMin: 0,  endHour: 16, endMin: 0,  type: 'study'   },
  { id: 'e5', title: 'Entrega TACS',         subtitle: 'Prazo limite',      day: 3, startHour: 11, startMin: 0,  endHour: 12, endMin: 0,  type: 'exam'    },
  { id: 'e6', title: 'Redes e Comunicações', subtitle: 'Sala C105',         day: 3, startHour: 14, startMin: 0,  endHour: 15, endMin: 30, type: 'study'   },
  { id: 'e7', title: 'Teste Redes',          subtitle: 'Sala C105 — Exame', day: 4, startHour: 9,  startMin: 0,  endHour: 11, endMin: 0,  type: 'exam'    },
  { id: 'e8', title: 'Revisão Sugerida',     subtitle: 'Foco: Módulo 3',    day: 1, startHour: 14, startMin: 0,  endHour: 15, endMin: 30, type: 'study', isAI: true },
]

const UPCOMING = [
  { id: 'u1', time: '09:00',       title: 'Algoritmia e Prog.',  dot: '#009957' },
  { id: 'u2', time: 'Sex · 09:00', title: 'Teste Redes',         dot: '#E53935' },
  { id: 'u3', time: 'Qua · 10:00', title: 'Estudo Cap. 4',       dot: '#9E9E9E' },
  { id: 'u4', time: 'Qua · 14:00', title: 'Bases de Dados',      dot: '#009957' },
]

const REMINDERS: Reminder[] = [
  { id: 'rem1', text: 'Rever slides de Algoritmia', tag: 'Hoje',    tagColor: '#009957' },
  { id: 'rem2', text: 'Entregar relatório TACS',    tag: 'Urgente', tagColor: '#E53935' },
  { id: 'rem3', text: 'Estudar capítulo 5 — BD',    tag: 'Amanhã',  tagColor: '#9E9E9E' },
]

const MONTHS_PT = [
  'Janeiro','Fevereiro','Março','Abril',
  'Maio','Junho','Julho','Agosto',
  'Setembro','Outubro','Novembro','Dezembro',
]

const DAY_IDX_TO_APR_DATE: Record<number, number> = { 0: 20, 1: 21, 2: 22, 3: 23, 4: 24 }
const MONTH_DAY_HEADERS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']

// ─── Helpers ──────────────────────────────────────────────────────────────────
function eventTop(startHour: number, startMin: number) {
  return (startHour - START_HOUR) * HOUR_HEIGHT + (startMin / 60) * HOUR_HEIGHT
}
function eventHeight(sh: number, sm: number, eh: number, em: number) {
  return ((eh * 60 + em - sh * 60 - sm) / 60) * HOUR_HEIGHT
}
function eventColors(type: EventType, isAI?: boolean) {
  let base: { bg: string; borderLeft: string; color: string }
  if (type === 'study')     base = { bg: 'rgba(0,153,87,0.10)',  borderLeft: '3px solid #009957', color: '#009957' }
  else if (type === 'exam') base = { bg: 'rgba(229,57,53,0.10)', borderLeft: '3px solid #E53935', color: '#E53935' }
  else                      base = { bg: '#F5F5F5',              borderLeft: '3px solid #BDBABA', color: '#656966' }

  if (isAI) {
    return {
      ...base,
      bg:         'repeating-linear-gradient(45deg, rgba(0,153,87,0.05), rgba(0,153,87,0.05) 8px, rgba(0,153,87,0.15) 8px, rgba(0,153,87,0.15) 16px)',
      borderLeft: '3px dashed #009957',
    }
  }
  return base
}
function fmtTime(h: number, m: number) {
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}
function buildMonthGrid(): (number | null)[] {
  const YEAR = 2026, MONTH = 3
  const firstDow    = new Date(YEAR, MONTH, 1).getDay()
  const daysInMonth = new Date(YEAR, MONTH + 1, 0).getDate()
  const cells: (number | null)[] = []
  for (let i = 0; i < firstDow;     i++) cells.push(null)
  for (let d = 1; d <= daysInMonth; d++) cells.push(d)
  while (cells.length % 7 !== 0)        cells.push(null)
  return cells
}

// ─── State ────────────────────────────────────────────────────────────────────
const currentView    = ref<ViewType>('Semana')
const isAddEventOpen = ref(false)
const events         = ref<CalEvent[]>([...INITIAL_EVENTS])
const filters        = ref({ aulas: true, estudo: true, exames: true })
const isFilterOpen   = ref(false)
const filterRef      = ref<HTMLDivElement | null>(null)
const completedRem   = ref<Set<string>>(new Set())
const form           = ref({ title: '', day: '0', startTime: '09:00', endTime: '10:00', type: 'study' as EventType })
const aiQuery        = ref('')
const isProcessingAI = ref(false)
const aiSuggestion   = ref<{ targetId: string; newDay: number; newStart: number; text: string } | null>(null)

// ─── Filter dropdown outside click ───────────────────────────────────────────
useOutsideClick(filterRef, () => { isFilterOpen.value = false }, isFilterOpen)

// ─── Derived ──────────────────────────────────────────────────────────────────
const visibleEvents = computed(() =>
  events.value.filter((ev) => {
    if (ev.type === 'study')   return filters.value.aulas
    if (ev.type === 'neutral') return filters.value.estudo
    if (ev.type === 'exam')    return filters.value.exames
    return true
  }),
)
const activeFilterCount = computed(() =>
  [filters.value.aulas, filters.value.estudo, filters.value.exames].filter(Boolean).length,
)
const allFiltersOn  = computed(() => activeFilterCount.value === 3)
const activeCols    = computed(() => currentView.value === 'Dia' ? TODAY_COL : WEEK_DAYS)
const periodLabel   = computed(() =>
  currentView.value === 'Dia'  ? 'Sexta-feira, 24 Abr 2026' :
  currentView.value === 'Ano'  ? '2026' :
                                  'Abr 20 – 24, 2026',
)
const monthCells    = computed(() => buildMonthGrid())
const eventsByDate  = computed(() => {
  const map: Record<number, CalEvent[]> = {}
  for (const ev of visibleEvents.value) {
    const date = DAY_IDX_TO_APR_DATE[ev.day]
    if (date !== undefined) {
      if (!map[date]) map[date] = []
      map[date].push(ev)
    }
  }
  return map
})

// ─── Actions ──────────────────────────────────────────────────────────────────
function toggleReminder(id: string) {
  const next = new Set(completedRem.value)
  next.has(id) ? next.delete(id) : next.add(id)
  completedRem.value = next
}

function handleSave() {
  if (!form.value.title.trim()) return
  const [sh, sm] = form.value.startTime.split(':').map(Number)
  const [eh, em] = form.value.endTime.split(':').map(Number)
  events.value = [
    ...events.value,
    {
      id:        `ev-${Date.now()}`,
      title:     form.value.title,
      day:       Number(form.value.day),
      startHour: sh, startMin: sm,
      endHour:   eh, endMin: em,
      type:      form.value.type,
    },
  ]
  isAddEventOpen.value = false
  form.value = { title: '', day: '0', startTime: '09:00', endTime: '10:00', type: 'study' }
}

function handleAISubmit() {
  if (!aiQuery.value.trim()) return
  isProcessingAI.value = true
  setTimeout(() => {
    isProcessingAI.value = false
    aiSuggestion.value = {
      targetId: 'e3',
      newDay:    4,
      newStart:  14,
      text:      "A IA sugere mover 'Estudo Cap. 4' para Sexta-feira às 14:00.",
    }
  }, 1500)
}

function handleAcceptAI() {
  if (!aiSuggestion.value) return
  events.value = events.value.map((ev) =>
    ev.id === aiSuggestion.value!.targetId
      ? { ...ev, day: aiSuggestion.value!.newDay, startHour: aiSuggestion.value!.newStart, isAI: true }
      : ev,
  )
  aiSuggestion.value = null
  aiQuery.value = ''
}

function handleRejectAI() {
  aiSuggestion.value = null
  aiQuery.value = ''
}

function toggleFilter(key: keyof typeof filters.value) {
  filters.value = { ...filters.value, [key]: !filters.value[key] }
}

function colEventsFor(dayIdx: number) {
  return visibleEvents.value.filter((e) => e.day === dayIdx)
}
</script>

<template>
  <div
    style="height: 100%; display: flex; overflow: hidden; padding: 24px; gap: 24px; background: #ffffff;"
  >

    <!-- ══ LEFT COLUMN 300px ══════════════════════════════════════════════════ -->
    <div
      style="width: 300px; flex-shrink: 0; display: flex; flex-direction: column; gap: 16px; overflow-y: auto;"
    >
      <!-- Title -->
      <div>
        <h1
          style="font-family: Inter, sans-serif; font-weight: 700; font-size: 24px; color: #1E1E1E; margin: 0; margin-bottom: 4px; line-height: 1.2;"
        >
          Calendário
        </h1>
        <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 12px; color: #9E9E9E; margin: 0;">
          1º semestre · Ano letivo 2026/2027
        </p>
      </div>

      <!-- Mini Calendar -->
      <MiniCalendar />

      <!-- Upcoming Events -->
      <WidgetCard title="Próximos Eventos">
        <div style="display: flex; flex-direction: column; gap: 12px;">
          <div
            v-for="ev in UPCOMING"
            :key="ev.id"
            style="display: flex; align-items: flex-start; gap: 10px;"
          >
            <div
              :style="{
                width: '8px',
                height: '8px',
                borderRadius: '50%',
                background: ev.dot,
                marginTop: '4px',
                flexShrink: 0,
              }"
            />
            <div style="min-width: 0;">
              <p
                style="font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #1E1E1E; margin: 0; margin-bottom: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
              >
                {{ ev.title }}
              </p>
              <span style="font-family: Inter, sans-serif; font-weight: 400; font-size: 11px; color: #BDBABA;">
                {{ ev.time }}
              </span>
            </div>
          </div>
        </div>
      </WidgetCard>

      <!-- Lembretes -->
      <WidgetCard title="Lembretes">
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <div
            v-for="rem in REMINDERS"
            :key="rem.id"
            :style="{
              display: 'flex',
              alignItems: 'center',
              gap: '10px',
              opacity: completedRem.has(rem.id) ? 0.5 : 1,
              transition: 'opacity 0.2s',
            }"
          >
            <button
              :style="{
                width: '18px',
                height: '18px',
                borderRadius: '50%',
                border: completedRem.has(rem.id) ? 'none' : '1.5px solid #BDBABA',
                background: completedRem.has(rem.id) ? '#009957' : 'transparent',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                cursor: 'pointer',
                flexShrink: 0,
                padding: 0,
                transition: 'background 0.15s, border 0.15s',
              }"
              @click="toggleReminder(rem.id)"
            >
              <Check v-if="completedRem.has(rem.id)" :size="9" :stroke-width="3" color="#ffffff" />
            </button>
            <div style="flex: 1; min-width: 0;">
              <span
                :style="{
                  fontFamily: 'Inter, sans-serif',
                  fontWeight: 400,
                  fontSize: '12px',
                  color: '#1E1E1E',
                  textDecoration: completedRem.has(rem.id) ? 'line-through' : 'none',
                  overflow: 'hidden',
                  textOverflow: 'ellipsis',
                  whiteSpace: 'nowrap',
                  display: 'block',
                }"
              >
                {{ rem.text }}
              </span>
            </div>
            <span
              :style="{
                fontFamily: 'Inter, sans-serif',
                fontWeight: 500,
                fontSize: '10px',
                color: rem.tagColor,
                background: rem.tagColor + '14',
                borderRadius: '4px',
                padding: '2px 6px',
                flexShrink: 0,
                whiteSpace: 'nowrap',
              }"
            >
              {{ rem.tag }}
            </span>
          </div>
        </div>
      </WidgetCard>
    </div>

    <!-- ══ RIGHT COLUMN ══════════════════════════════════════════════════════ -->
    <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; overflow: hidden;">

      <!-- ── AI Command Center ── -->
      <div
        :style="{
          borderRadius: '12px',
          border: `1px solid ${aiSuggestion ? '#009957' : '#E5E5E5'}`,
          background: aiSuggestion ? '#F4FBF7' : '#ffffff',
          padding: '10px 14px',
          marginBottom: '16px',
          flexShrink: 0,
          transition: 'border-color 0.2s, background 0.2s',
        }"
      >
        <!-- Input state -->
        <div
          v-if="!aiSuggestion && !isProcessingAI"
          style="display: flex; align-items: center; gap: 10px;"
        >
          <Sparkles :size="18" :stroke-width="1.8" color="#009957" style="flex-shrink: 0;" />
          <input
            v-model="aiQuery"
            type="text"
            placeholder="Ex: Reagenda o estudo de Bases de Dados para amanhã..."
            style="flex: 1; font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #1E1E1E; border: none; outline: none; background: transparent;"
            @keydown.enter="handleAISubmit"
          />
          <button
            class="transition-opacity hover:opacity-80"
            style="background: #009957; border: none; border-radius: 8px; padding: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"
            @click="handleAISubmit"
          >
            <Bot :size="16" :stroke-width="1.8" color="#ffffff" />
          </button>
        </div>

        <!-- Processing state -->
        <div
          v-else-if="isProcessingAI"
          style="display: flex; align-items: center; gap: 8px;"
        >
          <Sparkles :size="18" :stroke-width="1.8" color="#9E9E9E" style="flex-shrink: 0;" />
          <span
            class="animate-pulse"
            style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #9E9E9E;"
          >
            A analisar o teu horário...
          </span>
        </div>

        <!-- Suggestion state -->
        <div
          v-else-if="aiSuggestion"
          style="display: flex; align-items: center; justify-content: space-between; gap: 12px;"
        >
          <div style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0;">
            <Sparkles :size="18" :stroke-width="1.8" color="#009957" style="flex-shrink: 0;" />
            <span
              style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #009957; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
            >
              {{ aiSuggestion.text }}
            </span>
          </div>
          <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
            <button
              class="transition-colors hover:bg-[var(--color-surface-muted)]"
              style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; background: transparent; border: none; cursor: pointer; padding: 6px 4px; border-radius: 6px;"
              @click="handleRejectAI"
            >
              Cancelar
            </button>
            <button
              class="transition-opacity hover:opacity-80"
              style="font-family: Inter, sans-serif; font-weight: 600; font-size: 13px; color: #ffffff; background: #009957; border: none; border-radius: 8px; padding: 7px 16px; cursor: pointer;"
              @click="handleAcceptAI"
            >
              Aplicar
            </button>
          </div>
        </div>
      </div>

      <!-- ── Toolbar ── -->
      <div
        style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-shrink: 0; gap: 12px;"
      >
        <!-- Left: view toggle + filter -->
        <div style="display: flex; align-items: center; gap: 10px;">
          <!-- View toggle pill -->
          <div style="display: flex; background: #F5F5F5; border-radius: 10px; padding: 3px; gap: 2px;">
            <button
              v-for="v in (['Dia', 'Semana', 'Mês', 'Ano'] as ViewType[])"
              :key="v"
              :style="{
                fontFamily: 'Inter, sans-serif',
                fontWeight: v === currentView ? 600 : 400,
                fontSize: '13px',
                color: v === currentView ? '#009957' : '#9E9E9E',
                background: v === currentView ? '#EDF9EF' : 'transparent',
                border: 'none',
                borderRadius: '8px',
                padding: '6px 14px',
                cursor: 'pointer',
                transition: 'background 0.15s, color 0.15s',
                whiteSpace: 'nowrap',
              }"
              @click="currentView = v"
            >
              {{ v }}
            </button>
          </div>

          <!-- Filter button + dropdown -->
          <div ref="filterRef" style="position: relative;">
            <button
              class="transition-colors"
              :style="{
                display: 'flex',
                alignItems: 'center',
                gap: '6px',
                fontFamily: 'Inter, sans-serif',
                fontWeight: 500,
                fontSize: '13px',
                color: isFilterOpen || !allFiltersOn ? '#009957' : '#656966',
                background: isFilterOpen || !allFiltersOn ? '#EDF9EF' : '#F5F5F5',
                border: isFilterOpen || !allFiltersOn ? '1px solid #009957' : '1px solid transparent',
                borderRadius: '8px',
                padding: '6px 12px 6px 10px',
                cursor: 'pointer',
                transition: 'background 0.15s, color 0.15s',
              }"
              @click="isFilterOpen = !isFilterOpen"
            >
              <SlidersHorizontal :size="13" :stroke-width="2" />
              Filtrar
              <span
                v-if="!allFiltersOn"
                style="width: 16px; height: 16px; border-radius: 50%; background: #009957; color: #ffffff; font-family: Inter, sans-serif; font-weight: 700; font-size: 9px; display: flex; align-items: center; justify-content: center; margin-left: 2px;"
              >
                {{ activeFilterCount }}
              </span>
            </button>

            <div
              v-if="isFilterOpen"
              style="position: absolute; top: calc(100% + 6px); left: 0; width: 180px; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); z-index: 200; padding-top: 8px; padding-bottom: 8px; overflow: hidden;"
            >
              <button
                v-for="item in [
                  { key: 'aulas',  label: 'Aulas',  color: '#009957' },
                  { key: 'estudo', label: 'Estudo', color: '#656966' },
                  { key: 'exames', label: 'Exames', color: '#E53935' },
                ]"
                :key="item.key"
                class="transition-colors hover:bg-[var(--color-surface-muted)]"
                style="display: flex; align-items: center; gap: 10px; width: 100%; background: none; border: none; cursor: pointer; padding: 9px 14px; text-align: left;"
                @click="toggleFilter(item.key as keyof typeof filters)"
              >
                <div
                  :style="{
                    width: '16px',
                    height: '16px',
                    borderRadius: '4px',
                    border: filters[item.key as keyof typeof filters] ? 'none' : '1.5px solid #BDBABA',
                    background: filters[item.key as keyof typeof filters] ? item.color : '#ffffff',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flexShrink: 0,
                    transition: 'background 0.15s',
                  }"
                >
                  <Check
                    v-if="filters[item.key as keyof typeof filters]"
                    :size="9"
                    :stroke-width="3"
                    color="#ffffff"
                  />
                </div>
                <div
                  :style="{
                    width: '8px',
                    height: '8px',
                    borderRadius: '50%',
                    background: item.color,
                    flexShrink: 0,
                  }"
                />
                <span style="font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #1E1E1E;">
                  {{ item.label }}
                </span>
              </button>
            </div>
          </div>
        </div>

        <!-- Right: period nav + add button -->
        <div style="display: flex; align-items: center; gap: 10px;">
          <div style="display: flex; align-items: center; gap: 4px;">
            <button
              class="flex items-center justify-center transition-all hover:bg-[var(--color-surface-muted)]"
              style="width: 28px; height: 28px; background: none; border: 1px solid #E5E5E5; cursor: pointer; border-radius: 8px;"
            >
              <ChevronLeft :size="13" :stroke-width="2" color="#9E9E9E" />
            </button>
            <span
              style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E; padding-left: 6px; padding-right: 6px; white-space: nowrap;"
            >
              {{ periodLabel }}
            </span>
            <button
              class="flex items-center justify-center transition-all hover:bg-[var(--color-surface-muted)]"
              style="width: 28px; height: 28px; background: none; border: 1px solid #E5E5E5; cursor: pointer; border-radius: 8px;"
            >
              <ChevronRight :size="13" :stroke-width="2" color="#9E9E9E" />
            </button>
          </div>

          <button
            class="transition-opacity hover:opacity-80"
            style="display: flex; align-items: center; gap: 6px; font-family: Inter, sans-serif; font-weight: 600; font-size: 13px; color: #ffffff; background: #009957; border: none; border-radius: 10px; padding: 8px 16px 8px 12px; cursor: pointer;"
            @click="isAddEventOpen = true"
          >
            <Plus :size="14" :stroke-width="2.5" />
            Adicionar Evento
          </button>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════════════════
          CALENDAR BODY
      ══════════════════════════════════════════════════════════════════════ -->

      <!-- ── MONTH VIEW ── -->
      <div
        v-if="currentView === 'Mês'"
        style="flex: 1; overflow: hidden; border: 1px solid #E5E5E5; border-radius: 12px; display: flex; flex-direction: column;"
      >
        <!-- Day-of-week header -->
        <div
          style="display: grid; grid-template-columns: repeat(7, 1fr); flex-shrink: 0; border-bottom: 1px solid #E5E5E5; background: #FAFAFA;"
        >
          <div
            v-for="(d, i) in MONTH_DAY_HEADERS"
            :key="d"
            :style="{
              paddingTop: '10px',
              paddingBottom: '10px',
              textAlign: 'center',
              fontFamily: 'Inter, sans-serif',
              fontWeight: 500,
              fontSize: '11px',
              color: i === 0 || i === 6 ? '#BDBABA' : '#9E9E9E',
              letterSpacing: '0.04em',
              borderRight: i < 6 ? '1px solid #F0F0F0' : 'none',
            }"
          >
            {{ d }}
          </div>
        </div>
        <!-- Grid body -->
        <div style="flex: 1; overflow-y: auto;">
          <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #E5E5E5;">
            <div
              v-for="(date, idx) in monthCells"
              :key="idx"
              :style="{
                background: (idx % 7 === 0 || idx % 7 === 6) && date ? '#FAFAFA' : '#ffffff',
                minHeight: '100px',
                padding: '6px 8px',
                display: 'flex',
                flexDirection: 'column',
                gap: '3px',
              }"
            >
              <div v-if="date !== null" style="display: flex; justify-content: flex-end; margin-bottom: 2px;">
                <div
                  :style="{
                    width: '24px',
                    height: '24px',
                    borderRadius: '50%',
                    background: date === 24 ? '#009957' : 'transparent',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                  }"
                >
                  <span
                    :style="{
                      fontFamily: 'Inter, sans-serif',
                      fontWeight: date === 24 ? 700 : 400,
                      fontSize: '12px',
                      color: date === 24 ? '#ffffff' : (idx % 7 === 0 || idx % 7 === 6) ? '#BDBABA' : '#1E1E1E',
                    }"
                  >
                    {{ date }}
                  </span>
                </div>
              </div>
              <template v-if="date !== null">
                <div
                  v-for="ev in (eventsByDate[date] ?? [])"
                  :key="ev.id"
                  class="transition-opacity hover:opacity-75"
                  :title="ev.title"
                  :style="{
                    height: '20px',
                    borderRadius: '4px',
                    background: eventColors(ev.type, ev.isAI).bg,
                    borderLeft: eventColors(ev.type, ev.isAI).borderLeft,
                    display: 'flex',
                    alignItems: 'center',
                    paddingLeft: '5px',
                    paddingRight: '5px',
                    cursor: 'pointer',
                    overflow: 'hidden',
                    flexShrink: 0,
                  }"
                >
                  <span
                    :style="{
                      fontFamily: 'Inter, sans-serif',
                      fontWeight: 500,
                      fontSize: '10px',
                      color: eventColors(ev.type, ev.isAI).color,
                      overflow: 'hidden',
                      textOverflow: 'ellipsis',
                      whiteSpace: 'nowrap',
                      display: 'block',
                      width: '100%',
                    }"
                  >
                    {{ ev.title }}
                  </span>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- ── YEAR VIEW ── -->
      <div
        v-else-if="currentView === 'Ano'"
        style="flex: 1; overflow-y: auto; display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; padding: 4px;"
      >
        <div
          v-for="(month, mIdx) in MONTHS_PT"
          :key="month"
          class="transition-shadow hover:shadow-sm"
          style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 16px; cursor: pointer;"
        >
          <p
            :style="{
              fontFamily: 'Inter, sans-serif',
              fontWeight: 700,
              fontSize: '13px',
              color: mIdx === 3 ? '#009957' : '#1E1E1E',
              margin: 0,
              marginBottom: '12px',
            }"
          >
            {{ month }}
            <span
              v-if="mIdx === 3"
              style="font-family: Inter, sans-serif; font-weight: 500; font-size: 10px; color: #009957; margin-left: 6px;"
            >
              actual
            </span>
          </p>
          <!-- Mini weekday labels -->
          <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; margin-bottom: 4px;">
            <div
              v-for="(d, i) in ['D','S','T','Q','Q','S','S']"
              :key="i"
              style="font-family: Inter, sans-serif; font-weight: 400; font-size: 8px; color: #BDBABA; text-align: center;"
            >
              {{ d }}
            </div>
          </div>
          <!-- 35-dot heatmap -->
          <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;">
            <div
              v-for="i in 35"
              :key="i"
              :style="{
                width: '100%',
                aspectRatio: '1',
                borderRadius: '2px',
                background: i % 8 === 0 ? '#CBEAD7' : '#F5F5F5',
              }"
            />
          </div>
        </div>
      </div>

      <!-- ── DIA / SEMANA VIEW (CalendarGrid) ── -->
      <div
        v-else
        style="flex: 1; overflow: hidden; border: 1px solid #E5E5E5; border-radius: 12px; display: flex; flex-direction: column;"
      >
        <!-- Day header row -->
        <div
          style="display: flex; flex-shrink: 0; border-bottom: 1px solid #E5E5E5; background: #FAFAFA;"
        >
          <div :style="{ width: TIME_COL_W + 'px', flexShrink: 0 }" />
          <div
            v-for="(col, i) in activeCols"
            :key="col.label + col.date"
            :style="{
              flex: 1,
              paddingTop: '10px',
              paddingBottom: '10px',
              textAlign: 'center',
              borderLeft: i === 0 ? '1px solid #E5E5E5' : 'none',
              borderRight: i < activeCols.length - 1 ? '1px solid #F0F0F0' : 'none',
            }"
          >
            <span
              :style="{
                fontFamily: 'Inter, sans-serif',
                fontWeight: 500,
                fontSize: '11px',
                color: col.date === 24 ? '#009957' : '#9E9E9E',
                display: 'block',
                letterSpacing: '0.04em',
              }"
            >
              {{ col.label }}
            </span>
            <span
              :style="{
                fontFamily: 'Inter, sans-serif',
                fontWeight: col.date === 24 ? 700 : 500,
                fontSize: '16px',
                color: col.date === 24 ? '#009957' : '#1E1E1E',
                display: 'block',
                marginTop: '2px',
              }"
            >
              {{ col.date }}
            </span>
            <div
              v-if="col.date === 24"
              style="width: 5px; height: 5px; border-radius: 50%; background: #009957; margin: 3px auto 0;"
            />
          </div>
        </div>

        <!-- Scrollable body -->
        <div style="flex: 1; overflow-y: auto;">
          <div style="display: flex;">
            <!-- Time axis -->
            <div
              :style="{
                width: TIME_COL_W + 'px',
                flexShrink: 0,
                position: 'relative',
                height: TOTAL_GRID_HEIGHT + 'px',
              }"
            >
              <div
                v-for="h in HOURS"
                :key="h"
                :style="{
                  position: 'absolute',
                  top: ((h - START_HOUR) * HOUR_HEIGHT - 8) + 'px',
                  right: '8px',
                  fontFamily: 'Inter, sans-serif',
                  fontWeight: 400,
                  fontSize: '10px',
                  color: '#BDBABA',
                  userSelect: 'none',
                }"
              >
                {{ fmtTime(h, 0) }}
              </div>
            </div>

            <!-- Day columns -->
            <div style="flex: 1; display: flex; border-left: 1px solid #E5E5E5;">
              <div
                v-for="(col, ci) in activeCols"
                :key="col.label + col.date"
                :style="{
                  flex: 1,
                  position: 'relative',
                  height: TOTAL_GRID_HEIGHT + 'px',
                  borderRight: ci < activeCols.length - 1 ? '1px solid #F0F0F0' : 'none',
                }"
              >
                <!-- Hour grid lines -->
                <div
                  v-for="h in HOURS"
                  :key="h"
                  :style="{
                    position: 'absolute',
                    top: ((h - START_HOUR) * HOUR_HEIGHT) + 'px',
                    left: 0,
                    right: 0,
                    height: '1px',
                    background: h === START_HOUR ? 'transparent' : '#F5F5F5',
                  }"
                />
                <!-- Today column tint -->
                <div
                  v-if="col.date === 24"
                  style="position: absolute; inset: 0; background: rgba(0,153,87,0.018); pointer-events: none;"
                />
                <!-- Events -->
                <div
                  v-for="ev in colEventsFor(col.dayIdx)"
                  :key="ev.id"
                  class="transition-opacity hover:opacity-80"
                  :style="{
                    position: 'absolute',
                    top: (eventTop(ev.startHour, ev.startMin) + 2) + 'px',
                    left: '4px',
                    right: '4px',
                    height: (Math.max(eventHeight(ev.startHour, ev.startMin, ev.endHour, ev.endMin), 28) - 4) + 'px',
                    background: eventColors(ev.type, ev.isAI).bg,
                    borderLeft: eventColors(ev.type, ev.isAI).borderLeft,
                    borderRadius: '6px',
                    paddingTop: '5px',
                    paddingRight: '6px',
                    paddingBottom: '5px',
                    paddingLeft: '7px',
                    cursor: 'pointer',
                    overflow: 'hidden',
                  }"
                >
                  <!-- Title row with Sparkles icon when isAI -->
                  <div style="display: flex; align-items: center; gap: 2px; margin-bottom: 1px;">
                    <span
                      :style="{
                        fontFamily: 'Inter, sans-serif',
                        fontWeight: 600,
                        fontSize: '11px',
                        color: eventColors(ev.type, ev.isAI).color,
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                        flex: 1,
                        minWidth: 0,
                      }"
                    >
                      {{ ev.title }}
                    </span>
                    <Sparkles
                      v-if="ev.isAI"
                      :size="10"
                      :stroke-width="2"
                      :color="eventColors(ev.type, ev.isAI).color"
                      style="flex-shrink: 0;"
                    />
                  </div>
                  <p
                    v-if="ev.subtitle && Math.max(eventHeight(ev.startHour, ev.startMin, ev.endHour, ev.endMin), 28) > 36"
                    :style="{
                      fontFamily: 'Inter, sans-serif',
                      fontWeight: 400,
                      fontSize: '10px',
                      color: eventColors(ev.type, ev.isAI).color,
                      opacity: 0.75,
                      margin: 0,
                      overflow: 'hidden',
                      textOverflow: 'ellipsis',
                      whiteSpace: 'nowrap',
                    }"
                  >
                    {{ ev.subtitle }}
                  </p>
                  <p
                    v-if="Math.max(eventHeight(ev.startHour, ev.startMin, ev.endHour, ev.endMin), 28) > 52"
                    :style="{
                      fontFamily: 'Inter, sans-serif',
                      fontWeight: 400,
                      fontSize: '10px',
                      color: eventColors(ev.type, ev.isAI).color,
                      opacity: 0.6,
                      margin: 0,
                      marginTop: '2px',
                    }"
                  >
                    {{ fmtTime(ev.startHour, ev.startMin) }} – {{ fmtTime(ev.endHour, ev.endMin) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ ADD EVENT MODAL ══════════════════════════════════════════════════════ -->
  <Teleport to="body">
    <div
      v-if="isAddEventOpen"
      style="position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 1000; display: flex; align-items: center; justify-content: center;"
      @click.self="isAddEventOpen = false"
    >
      <div
        style="width: 400px; background: #ffffff; border-radius: 16px; padding: 24px; box-shadow: 0 8px 40px rgba(0,0,0,0.12); display: flex; flex-direction: column; gap: 16px;"
        @click.stop
      >
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <h2
            style="font-family: Inter, sans-serif; font-weight: 700; font-size: 18px; color: #1E1E1E; margin: 0;"
          >
            Adicionar Evento
          </h2>
          <button
            class="flex items-center justify-center transition-all hover:bg-[var(--color-surface-muted)]"
            style="width: 32px; height: 32px; background: none; border: 1px solid #E5E5E5; cursor: pointer; border-radius: 8px;"
            @click="isAddEventOpen = false"
          >
            <X :size="14" :stroke-width="2" color="#9E9E9E" />
          </button>
        </div>

        <!-- Nome do evento -->
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #656966;">
            Nome do evento
          </label>
          <input
            v-model="form.title"
            type="text"
            placeholder="ex: Algoritmia e Prog."
            style="font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #1E1E1E; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; outline: none; width: 100%; box-sizing: border-box;"
          />
        </div>

        <!-- Dia da semana -->
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #656966;">
            Dia da semana
          </label>
          <select
            v-model="form.day"
            style="font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #1E1E1E; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; outline: none; width: 100%; box-sizing: border-box; cursor: pointer;"
          >
            <option
              v-for="d in WEEK_DAYS"
              :key="d.dayIdx"
              :value="String(d.dayIdx)"
            >
              {{ d.label }} {{ d.date }} Abr
            </option>
          </select>
        </div>

        <!-- Início + Fim -->
        <div style="display: flex; gap: 12px;">
          <div style="display: flex; flex-direction: column; gap: 6px; flex: 1;">
            <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #656966;">Início</label>
            <input
              v-model="form.startTime"
              type="time"
              style="font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #1E1E1E; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; outline: none; width: 100%; box-sizing: border-box;"
            />
          </div>
          <div style="display: flex; flex-direction: column; gap: 6px; flex: 1;">
            <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #656966;">Fim</label>
            <input
              v-model="form.endTime"
              type="time"
              style="font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #1E1E1E; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; outline: none; width: 100%; box-sizing: border-box;"
            />
          </div>
        </div>

        <!-- Tipo -->
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #656966;">Tipo</label>
          <select
            v-model="form.type"
            style="font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #1E1E1E; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; outline: none; width: 100%; box-sizing: border-box; cursor: pointer;"
          >
            <option value="study">Aula / Estudo</option>
            <option value="exam">Exame / Prazo</option>
            <option value="neutral">Outro</option>
          </select>
        </div>

        <!-- Actions -->
        <div style="display: flex; gap: 10px; margin-top: 4px;">
          <button
            class="transition-colors hover:bg-[var(--color-surface-muted)]"
            style="flex: 1; font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 10px; padding: 10px 0; cursor: pointer;"
            @click="isAddEventOpen = false"
          >
            Cancelar
          </button>
          <button
            class="transition-opacity hover:opacity-80"
            :style="{
              flex: 1,
              fontFamily: 'Inter, sans-serif',
              fontWeight: 600,
              fontSize: '14px',
              color: '#ffffff',
              background: form.title.trim() ? '#009957' : '#C5C5C5',
              border: 'none',
              borderRadius: '10px',
              padding: '10px 0',
              cursor: form.title.trim() ? 'pointer' : 'not-allowed',
              transition: 'background 0.15s',
            }"
            @click="handleSave"
          >
            Guardar
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
