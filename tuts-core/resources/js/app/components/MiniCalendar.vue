<script setup lang="ts">
import { ref, computed } from 'vue'
import { ChevronLeft, ChevronRight } from '@lucide/vue'

defineOptions({ name: 'MiniCalendar' })

interface CalEvent {
  date: string   // YYYY-MM-DD
  color?: string
}

const props = defineProps<{
  events?: CalEvent[]
  selectedDate?: string
}>()

const emit = defineEmits<{
  selectDate: [date: string]
}>()

const today = new Date()
const currentMonth = ref(today.getMonth())
const currentYear  = ref(today.getFullYear())

const MONTH_NAMES = [
  'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
]
const DAY_LABELS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']

const monthLabel = computed(() => `${MONTH_NAMES[currentMonth.value]} ${currentYear.value}`)

function prevMonth() {
  if (currentMonth.value === 0) { currentMonth.value = 11; currentYear.value -= 1 }
  else currentMonth.value -= 1
}
function nextMonth() {
  if (currentMonth.value === 11) { currentMonth.value = 0; currentYear.value += 1 }
  else currentMonth.value += 1
}

// Build Mon-first grid (6 rows × 7 cols = 42 cells)
const calendarDays = computed(() => {
  const y = currentYear.value
  const m = currentMonth.value

  // First day of month (0=Sun … 6=Sat) → convert to Mon-first offset (0=Mon … 6=Sun)
  const firstDow = new Date(y, m, 1).getDay()          // 0=Sun
  const offset   = firstDow === 0 ? 6 : firstDow - 1   // Mon-first
  const daysInMonth = new Date(y, m + 1, 0).getDate()

  const cells: Array<{ day: number | null; iso: string | null }> = []
  for (let i = 0; i < offset; i++) cells.push({ day: null, iso: null })
  for (let d = 1; d <= daysInMonth; d++) {
    const iso = `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    cells.push({ day: d, iso })
  }
  while (cells.length < 42) cells.push({ day: null, iso: null })
  return cells
})

function isToday(iso: string | null): boolean {
  if (!iso) return false
  const t = today
  return (
    iso ===
    `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`
  )
}

function isSelected(iso: string | null): boolean {
  return !!iso && iso === props.selectedDate
}

function dotsFor(iso: string | null): string[] {
  if (!iso || !props.events) return []
  return (props.events ?? [])
    .filter((e) => e.date === iso)
    .map((e) => e.color ?? '#009957')
    .slice(0, 3)
}

function select(iso: string | null) {
  if (!iso) return
  emit('selectDate', iso)
}
</script>

<template>
  <div style="font-family: Inter, sans-serif; user-select: none;">
    <!-- Month nav -->
    <div
      style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;"
    >
      <button
        class="transition-opacity hover:opacity-60"
        style="background: none; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center;"
        @click="prevMonth"
      >
        <ChevronLeft :size="16" :stroke-width="2" color="#656966" />
      </button>
      <span style="font-weight: 600; font-size: 13px; color: #1E1E1E;">
        {{ monthLabel }}
      </span>
      <button
        class="transition-opacity hover:opacity-60"
        style="background: none; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center;"
        @click="nextMonth"
      >
        <ChevronRight :size="16" :stroke-width="2" color="#656966" />
      </button>
    </div>

    <!-- Day labels -->
    <div style="display: grid; grid-template-columns: repeat(7, 1fr); margin-bottom: 4px;">
      <div
        v-for="label in DAY_LABELS"
        :key="label"
        style="text-align: center; font-weight: 500; font-size: 10px; color: #BDBABA; padding: 4px 0;"
      >
        {{ label }}
      </div>
    </div>

    <!-- Day cells -->
    <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;">
      <div
        v-for="(cell, i) in calendarDays"
        :key="i"
        :style="{
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          padding: '4px 0',
          cursor: cell.iso ? 'pointer' : 'default',
          borderRadius: '7px',
          background: isSelected(cell.iso) ? '#009957' : isToday(cell.iso) ? '#EDF9EF' : 'transparent',
        }"
        @click="select(cell.iso)"
      >
        <span
          :style="{
            fontSize: '12px',
            fontWeight: isToday(cell.iso) || isSelected(cell.iso) ? '700' : '400',
            color: isSelected(cell.iso) ? '#ffffff' : isToday(cell.iso) ? '#009957' : cell.iso ? '#1E1E1E' : 'transparent',
          }"
        >
          {{ cell.day ?? '' }}
        </span>
        <!-- Event dots -->
        <div
          v-if="dotsFor(cell.iso).length"
          style="display: flex; gap: 2px; margin-top: 2px;"
        >
          <div
            v-for="(color, di) in dotsFor(cell.iso)"
            :key="di"
            :style="{
              width: '4px',
              height: '4px',
              borderRadius: '50%',
              background: isSelected(cell.iso) ? 'rgba(255,255,255,0.8)' : color,
            }"
          />
        </div>
      </div>
    </div>
  </div>
</template>
