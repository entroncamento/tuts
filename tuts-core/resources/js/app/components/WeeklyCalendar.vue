<script setup lang="ts">
import { computed } from 'vue'

defineOptions({ name: 'WeeklyCalendar' })

interface CalEvent {
  id:        string
  title:     string
  day:       number   // 0=Mon … 6=Sun
  startHour: number   // e.g. 9.0 = 09:00, 9.5 = 09:30
  endHour:   number
  color?:    string
}

const props = defineProps<{
  events?: CalEvent[]
}>()

// Hours shown: 08:00 – 19:00 (11 slots)
const HOUR_START = 8
const HOUR_END   = 19
const TOTAL_HOURS = HOUR_END - HOUR_START
const CELL_HEIGHT = 56   // px per hour

const DAYS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']

const EVENT_COLORS = [
  { bg: '#EDF9EF', border: '#009957', text: '#009957' },
  { bg: '#EBF3FB', border: '#2563EB', text: '#2563EB' },
  { bg: '#FEF3E8', border: '#F59E0B', text: '#F59E0B' },
  { bg: '#FDE8E8', border: '#E53935', text: '#E53935' },
  { bg: '#F3E8FD', border: '#7C3AED', text: '#7C3AED' },
]

const today = new Date()
// 0=Mon … 6=Sun (Monday-first index)
const todayDowMon = today.getDay() === 0 ? 6 : today.getDay() - 1

const hours = computed(() => {
  const list: string[] = []
  for (let h = HOUR_START; h < HOUR_END; h++) {
    list.push(`${String(h).padStart(2, '0')}:00`)
  }
  return list
})

function eventTop(ev: CalEvent): string {
  return `${(ev.startHour - HOUR_START) * CELL_HEIGHT}px`
}
function eventHeight(ev: CalEvent): string {
  return `${(ev.endHour - ev.startHour) * CELL_HEIGHT - 2}px`
}
function colorFor(ev: CalEvent, i: number) {
  if (ev.color) return { bg: ev.color + '22', border: ev.color, text: ev.color }
  return EVENT_COLORS[i % EVENT_COLORS.length]
}
function eventsForDay(day: number): (CalEvent & { _idx: number })[] {
  return (props.events ?? [])
    .map((e, i) => ({ ...e, _idx: i }))
    .filter((e) => e.day === day)
}
</script>

<template>
  <div style="font-family: Inter, sans-serif; overflow: hidden;">
    <!-- Day header row -->
    <div
      style="display: grid; grid-template-columns: 52px repeat(7, 1fr); border-bottom: 1px solid #E5E5E5; margin-bottom: 0;"
    >
      <div />
      <div
        v-for="(day, i) in DAYS"
        :key="day"
        :style="{
          textAlign: 'center',
          padding: '8px 0',
          fontWeight: i === todayDowMon ? '700' : '500',
          fontSize: '12px',
          color: i === todayDowMon ? '#009957' : '#9E9E9E',
        }"
      >
        {{ day }}
      </div>
    </div>

    <!-- Scrollable time grid -->
    <div style="overflow-y: auto; max-height: 480px;">
      <div
        :style="{
          display: 'grid',
          gridTemplateColumns: '52px repeat(7, 1fr)',
          position: 'relative',
        }"
      >
        <!-- Hour labels + horizontal lines -->
        <template v-for="(hour, hi) in hours" :key="hour">
          <!-- Hour label -->
          <div
            :style="{
              height: `${CELL_HEIGHT}px`,
              display: 'flex',
              alignItems: 'flex-start',
              paddingTop: '4px',
              paddingRight: '8px',
              justifyContent: 'flex-end',
              fontSize: '10px',
              color: '#BDBABA',
              fontWeight: '400',
              borderTop: hi > 0 ? '1px solid #F0F0F0' : 'none',
              boxSizing: 'border-box',
            }"
          >
            {{ hour }}
          </div>

          <!-- Day cells for this hour -->
          <div
            v-for="(_, di) in DAYS"
            :key="di"
            :style="{
              height: `${CELL_HEIGHT}px`,
              borderLeft: '1px solid #F0F0F0',
              borderTop: hi > 0 ? '1px solid #F0F0F0' : 'none',
              position: 'relative',
              background: di === todayDowMon ? 'rgba(0,153,87,0.02)' : 'transparent',
            }"
          />
        </template>

        <!-- Events overlay — positioned over the grid -->
        <template v-for="(day, di) in DAYS" :key="di">
          <!-- Absolutely positioned column over column di+1 (1-indexed) -->
          <div
            :style="{
              position: 'absolute',
              top: 0,
              left: `calc(52px + ${di} * ((100% - 52px) / 7))`,
              width: `calc((100% - 52px) / 7)`,
              height: `${TOTAL_HOURS * CELL_HEIGHT}px`,
              pointerEvents: 'none',
              paddingLeft: '2px',
              paddingRight: '2px',
              boxSizing: 'border-box',
            }"
          >
            <div
              v-for="ev in eventsForDay(di)"
              :key="ev.id"
              :style="{
                position: 'absolute',
                top: eventTop(ev),
                height: eventHeight(ev),
                left: '2px',
                right: '2px',
                borderRadius: '6px',
                background: colorFor(ev, ev._idx).bg,
                borderLeft: `3px solid ${colorFor(ev, ev._idx).border}`,
                padding: '4px 6px',
                overflow: 'hidden',
                pointerEvents: 'all',
                cursor: 'default',
                boxSizing: 'border-box',
              }"
            >
              <p
                :style="{
                  fontFamily: 'Inter, sans-serif',
                  fontWeight: 600,
                  fontSize: '11px',
                  color: colorFor(ev, ev._idx).text,
                  margin: 0,
                  overflow: 'hidden',
                  textOverflow: 'ellipsis',
                  whiteSpace: 'nowrap',
                  lineHeight: 1.3,
                }"
              >
                {{ ev.title }}
              </p>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
