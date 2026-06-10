<script setup lang="ts">
import { ref, computed } from 'vue'
import {
  Brain, AlertTriangle, TrendingDown, MessageSquare,
  Sparkles, ChevronDown, Users, BookOpen,
  Check, X, SlidersHorizontal, RotateCcw,
} from '@lucide/vue'
import RoleGuard from '@/app/components/RoleGuard.vue'

// ─── Mock data ────────────────────────────────────────────────────────────────
const MOCK_UCS = ['Redes de Computadores', 'Algoritmia e Prog.', 'Bases de Dados']

const AI_INSIGHTS = [
  {
    id:         'ai1',
    topic:      'Sub-redes e CIDR',
    risk:       'high' as const,
    suggestion: 'Reforçar com exercícios práticos de cálculo de máscaras de rede. 68% dos estudantes errou este tema no último simulacro.',
  },
  {
    id:         'ai2',
    topic:      'Modelo OSI — Camada de Transporte',
    risk:       'medium' as const,
    suggestion: 'Clarificar diferenças entre TCP e UDP com exemplos de aplicações reais (streaming vs transferência de ficheiros).',
  },
  {
    id:         'ai3',
    topic:      'Algoritmos de Routing',
    risk:       'high' as const,
    suggestion: 'Rever Dijkstra com uma animação passo-a-passo na próxima aula. Taxa de erro acima dos 71%.',
  },
]

const THEME_DIFFICULTIES = [
  { id: 't1', theme: 'Sub-redes (CIDR)',     failRate: 72, color: '#E53935' },
  { id: 't2', theme: 'Routing Dinâmico',      failRate: 64, color: '#E53935' },
  { id: 't3', theme: 'Modelo OSI',            failRate: 58, color: '#F57C00' },
  { id: 't4', theme: 'Segurança — Firewalls', failRate: 45, color: '#F57C00' },
  { id: 't5', theme: 'HTTP & DNS',            failRate: 31, color: '#009957' },
]

// ─── KPI config ───────────────────────────────────────────────────────────────
const KPI_CARDS = [
  { id: 'kpi1', label: 'Estudantes Ativos',       value: '47',  delta: '+3 esta semana',   Icon: Users,         color: '#009957', bg: 'rgba(0,153,87,0.07)'    },
  { id: 'kpi2', label: 'Sessões de Estudo',        value: '183', delta: 'nas últimas 48h',  Icon: BookOpen,      color: '#1E3A8A', bg: 'rgba(30,58,138,0.07)'   },
  { id: 'kpi3', label: 'Alertas de Desempenho',    value: '8',   delta: 'requerem atenção', Icon: AlertTriangle, color: '#E53935', bg: 'rgba(229,57,53,0.07)'   },
]

// ─── AI Curated Doubts ────────────────────────────────────────────────────────
type DoubtStatus = 'pending' | 'approved' | 'rejected'
interface AICuratedDoubt {
  id:              string
  theme:           string
  exampleQuestion: string
  freq:            number
  status:          DoubtStatus
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
const RISK_META = {
  high:   { label: 'Alto Risco',  color: '#E53935', bg: 'rgba(229,57,53,0.10)'  },
  medium: { label: 'Médio Risco', color: '#F57C00', bg: 'rgba(245,124,0,0.10)'  },
} as const

// ─── State ────────────────────────────────────────────────────────────────────
const selectedUC = ref(MOCK_UCS[0])
const ucOpen     = ref(false)

const aiDoubts = ref<AICuratedDoubt[]>([
  { id: 'd1', theme: 'Cálculo de Sub-redes', exampleQuestion: 'Como calcular a máscara se pedir 50 hosts?',  freq: 24, status: 'approved' },
  { id: 'd2', theme: 'TCP vs UDP',            exampleQuestion: 'Qual a diferença prática no streaming?',       freq: 18, status: 'approved' },
  { id: 'd3', theme: 'Pedidos de Resumo',     exampleQuestion: 'Podes resumir o capítulo 3?',                 freq: 45, status: 'pending'  },
  { id: 'd4', theme: 'Fragmentação IPv4',     exampleQuestion: 'Como funciona o offset na fragmentação?',     freq: 12, status: 'pending'  },
])

const curationModalOpen = ref(false)

// ─── Derived ──────────────────────────────────────────────────────────────────
const approvedDoubts = computed(() =>
  aiDoubts.value
    .filter((d) => d.status === 'approved')
    .sort((a, b) => b.freq - a.freq),
)

const pendingDoubts = computed(() =>
  aiDoubts.value
    .filter((d) => d.status === 'pending')
    .sort((a, b) => b.freq - a.freq),
)

// ─── Actions ──────────────────────────────────────────────────────────────────
function handleCurateDoubt(id: string, newStatus: DoubtStatus) {
  aiDoubts.value = aiDoubts.value.map((d) => (d.id === id ? { ...d, status: newStatus } : d))
}
</script>

<template>
  <RoleGuard required="teacher">

    <!-- ════════════════════════════════════════════════════════════════
        MAIN SCROLLABLE AREA
    ════════════════════════════════════════════════════════════════ -->
    <div
      style="height: 100%; overflow-y: auto; background: #ffffff; padding: 28px 28px 48px; font-family: Inter, sans-serif;"
    >

      <!-- ── Header ── -->
      <div
        style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 28px; gap: 16px;"
      >
        <div>
          <h1
            style="font-weight: 700; font-size: 24px; color: #1E1E1E; margin: 0; margin-bottom: 4px; line-height: 1.2;"
          >
            Dashboard Pedagógico
          </h1>
          <p style="font-weight: 400; font-size: 13px; color: #9E9E9E; margin: 0;">
            Monitorização de dificuldades e padrões de aprendizagem
          </p>
        </div>

        <!-- UC selector dropdown -->
        <div style="position: relative; flex-shrink: 0;">
          <button
            style="display: flex; align-items: center; gap: 10px; font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 10px; padding: 9px 14px; cursor: pointer; transition: border-color 0.15s; min-width: 220px; justify-content: space-between;"
            @click="ucOpen = !ucOpen"
          >
            <span style="display: flex; align-items: center; gap: 8px;">
              <BookOpen :size="14" :stroke-width="1.8" color="#009957" />
              {{ selectedUC }}
            </span>
            <ChevronDown
              :size="14"
              color="#9E9E9E"
              :style="{ transform: ucOpen ? 'rotate(180deg)' : 'rotate(0deg)', transition: 'transform 0.2s' }"
            />
          </button>

          <div
            v-if="ucOpen"
            style="position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 10px; box-shadow: 0 6px 24px rgba(0,0,0,0.08); z-index: 100; overflow: hidden; padding-top: 6px; padding-bottom: 6px;"
          >
            <button
              v-for="uc in MOCK_UCS"
              :key="uc"
              :style="{
                display: 'block',
                width: '100%',
                textAlign: 'left',
                fontFamily: 'Inter, sans-serif',
                fontWeight: uc === selectedUC ? 600 : 400,
                fontSize: '13px',
                color: uc === selectedUC ? '#009957' : '#1E1E1E',
                background: uc === selectedUC ? '#EDF9EF' : 'none',
                border: 'none',
                padding: '9px 14px',
                cursor: 'pointer',
              }"
              @click="selectedUC = uc; ucOpen = false"
            >
              {{ uc }}
            </button>
          </div>
        </div>
      </div>

      <!-- ── KPI Cards (3 cols) ── -->
      <div
        style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;"
      >
        <div
          v-for="kpi in KPI_CARDS"
          :key="kpi.id"
          style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 14px; padding: 20px 22px; display: flex; flex-direction: column; gap: 12px;"
        >
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <span style="font-weight: 500; font-size: 12px; color: #9E9E9E;">{{ kpi.label }}</span>
            <div
              :style="{
                width: '34px',
                height: '34px',
                borderRadius: '9px',
                background: kpi.bg,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flexShrink: 0,
              }"
            >
              <component :is="kpi.Icon" :size="17" :stroke-width="1.8" :color="kpi.color" />
            </div>
          </div>
          <div>
            <p style="font-weight: 700; font-size: 32px; color: #1E1E1E; margin: 0; line-height: 1;">
              {{ kpi.value }}
            </p>
            <p :style="{ fontWeight: 400, fontSize: '11px', color: kpi.color, margin: 0, marginTop: '4px' }">
              {{ kpi.delta }}
            </p>
          </div>
        </div>
      </div>

      <!-- ── AI Insights Banner ── -->
      <div
        style="background: #F4FBF7; border: 1px solid #009957; border-radius: 14px; padding: 20px 22px; margin-bottom: 24px;"
      >
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
          <div
            style="width: 34px; height: 34px; border-radius: 9px; background: rgba(0,153,87,0.12); display: flex; align-items: center; justify-content: center; flex-shrink: 0;"
          >
            <Brain :size="17" :stroke-width="1.8" color="#009957" />
          </div>
          <div>
            <p
              style="font-weight: 700; font-size: 14px; color: #009957; margin: 0; margin-bottom: 1px; display: flex; align-items: center; gap: 6px;"
            >
              Análise e Sugestões da IA
              <Sparkles :size="13" :stroke-width="2" color="#009957" />
            </p>
            <p style="font-weight: 400; font-size: 11px; color: #009957; opacity: 0.7; margin: 0;">
              {{ AI_INSIGHTS.length }} tópicos críticos identificados para a próxima aula
            </p>
          </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
          <div
            v-for="ins in AI_INSIGHTS"
            :key="ins.id"
            style="background: #ffffff; border: 1px solid rgba(0,153,87,0.18); border-radius: 10px; padding: 12px 16px; display: flex; align-items: flex-start; gap: 12px;"
          >
            <span
              :style="{
                fontWeight: 600,
                fontSize: '10px',
                color: RISK_META[ins.risk].color,
                background: RISK_META[ins.risk].bg,
                borderRadius: '4px',
                padding: '3px 7px',
                whiteSpace: 'nowrap',
                flexShrink: 0,
                marginTop: '1px',
              }"
            >
              {{ RISK_META[ins.risk].label }}
            </span>
            <div style="flex: 1; min-width: 0;">
              <p style="font-weight: 600; font-size: 13px; color: #1E1E1E; margin: 0; margin-bottom: 3px;">
                {{ ins.topic }}
              </p>
              <p style="font-weight: 400; font-size: 12px; color: #656966; margin: 0; line-height: 1.5;">
                {{ ins.suggestion }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Analytics: Error Patterns + AI Curated Doubts ── -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

        <!-- Left: Error patterns -->
        <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 14px; padding: 20px 22px;">
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px;">
            <TrendingDown :size="16" :stroke-width="1.8" color="#E53935" />
            <p style="font-weight: 700; font-size: 14px; color: #1E1E1E; margin: 0;">
              Padrões de Erro por Tema
            </p>
          </div>

          <div style="display: flex; flex-direction: column; gap: 14px;">
            <div v-for="td in THEME_DIFFICULTIES" :key="td.id">
              <div
                style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;"
              >
                <span style="font-weight: 400; font-size: 12px; color: #1E1E1E;">{{ td.theme }}</span>
                <span :style="{ fontWeight: 600, fontSize: '12px', color: td.color }">
                  {{ td.failRate }}%
                </span>
              </div>
              <div style="height: 7px; border-radius: 99px; background: #F5F5F5; overflow: hidden;">
                <div
                  :style="{
                    height: '100%',
                    borderRadius: '99px',
                    background: td.color,
                    width: `${td.failRate}%`,
                    transition: 'width 0.6s ease',
                  }"
                />
              </div>
            </div>
          </div>

          <div style="display: flex; gap: 16px; margin-top: 18px;">
            <div
              v-for="legend in [
                { color: '#E53935', label: 'Alto  ≥ 60%' },
                { color: '#F57C00', label: 'Médio 40–59%' },
                { color: '#009957', label: 'Baixo  < 40%' },
              ]"
              :key="legend.label"
              style="display: flex; align-items: center; gap: 5px;"
            >
              <div :style="{ width: '8px', height: '8px', borderRadius: '2px', background: legend.color }" />
              <span style="font-weight: 400; font-size: 10px; color: #9E9E9E;">{{ legend.label }}</span>
            </div>
          </div>
        </div>

        <!-- Right: AI Curated Doubts widget -->
        <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 14px; padding: 20px 22px;">

          <!-- Widget header -->
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px;">
            <MessageSquare :size="16" :stroke-width="1.8" color="#1E3A8A" />
            <p style="font-weight: 700; font-size: 14px; color: #1E1E1E; margin: 0; flex: 1;">
              Tópicos de Dúvida (Filtrados)
            </p>
            <button
              style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #1E3A8A; background: rgba(30,58,138,0.10); border: none; padding: 6px 12px; border-radius: 8px; cursor: pointer; flex-shrink: 0;"
              @click="curationModalOpen = true"
            >
              <SlidersHorizontal :size="14" color="#1E3A8A" />
              Curadoria IA
              <span
                v-if="pendingDoubts.length > 0"
                style="background: #E53935; color: #ffffff; border-radius: 99px; padding: 2px 6px; font-size: 10px; font-weight: 700; line-height: 1.4;"
              >
                {{ pendingDoubts.length }}
              </span>
            </button>
          </div>

          <!-- Approved doubts list with Undo action -->
          <p
            v-if="approvedDoubts.length === 0"
            style="font-size: 12px; color: #9E9E9E; text-align: center; padding: 24px 0; margin: 0; line-height: 1.6;"
          >
            Nenhum tópico aprovado.<br />
            Clique em <strong>Curadoria IA</strong> para rever dúvidas.
          </p>

          <div v-else style="display: flex; flex-direction: column; gap: 0;">
            <div
              v-for="(doubt, idx) in approvedDoubts"
              :key="doubt.id"
              :style="{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: '12px',
                paddingTop: '12px',
                paddingBottom: '12px',
                borderTop: idx === 0 ? 'none' : '1px solid #F5F5F5',
              }"
            >
              <!-- Left: rank + text block -->
              <div style="display: flex; align-items: flex-start; gap: 12px; flex: 1; min-width: 0;">
                <span
                  :style="{
                    fontWeight: 700,
                    fontSize: '12px',
                    color: idx === 0 ? '#009957' : '#BDBABA',
                    width: '18px',
                    flexShrink: 0,
                    textAlign: 'center',
                    marginTop: '2px',
                  }"
                >
                  {{ idx + 1 }}
                </span>
                <div style="flex: 1; min-width: 0;">
                  <p style="font-weight: 500; font-size: 13px; color: #1E1E1E; margin: 0; margin-bottom: 3px;">
                    {{ doubt.theme }}
                  </p>
                  <p
                    style="font-weight: 400; font-size: 11px; color: #9E9E9E; font-style: italic; margin: 0; line-height: 1.4; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"
                  >
                    "{{ doubt.exampleQuestion }}"
                  </p>
                </div>
              </div>

              <!-- Right: frequency badge + undo button -->
              <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                <span
                  style="font-weight: 700; font-size: 11px; color: #1E3A8A; background: rgba(30,58,138,0.08); border-radius: 6px; padding: 3px 8px; white-space: nowrap;"
                >
                  {{ doubt.freq }}×
                </span>
                <button
                  title="Devolver à curadoria"
                  style="background: none; border: none; cursor: pointer; padding: 6px; border-radius: 6px; color: #9E9E9E; display: flex; align-items: center; justify-content: center;"
                  @click="handleCurateDoubt(doubt.id, 'pending')"
                >
                  <RotateCcw :size="14" color="#9E9E9E" />
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════
        MODAL — TREINAR IA: CURADORIA DE DÚVIDAS
        maxWidth: 1100px · CSS Grid body · Square-ish card layout
    ════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="curationModalOpen"
        style="position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 100; padding: 24px;"
        @click="curationModalOpen = false"
      >
        <!-- White modal box — maxWidth: 1100px -->
        <div
          style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 1100px; display: flex; flex-direction: column; max-height: 80vh; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.2);"
          @click.stop
        >
          <!-- Header -->
          <div
            style="padding: 24px; border-bottom: 1px solid #E5E5E5; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;"
          >
            <div>
              <h3
                style="font-family: Inter, sans-serif; font-weight: 700; font-size: 18px; color: #1E1E1E; margin: 0;"
              >
                Treinar IA: Curadoria de Dúvidas
              </h3>
              <p
                style="font-family: Inter, sans-serif; font-size: 13px; color: #656966; margin: 0; margin-top: 4px;"
              >
                Aprove tópicos relevantes. O sistema filtra e aprende com os ignorados.
                <span
                  v-if="pendingDoubts.length > 0"
                  style="margin-left: 8px; color: #1E3A8A; font-weight: 600;"
                >
                  {{ pendingDoubts.length }} pendente{{ pendingDoubts.length !== 1 ? 's' : '' }}
                </span>
              </p>
            </div>
            <button
              style="background: none; border: none; cursor: pointer; padding: 4px;"
              @click="curationModalOpen = false"
            >
              <X :size="20" color="#9E9E9E" />
            </button>
          </div>

          <!-- Body -->
          <div style="padding: 24px; overflow-y: auto; flex: 1;">
            <!-- All-clear empty state -->
            <div
              v-if="pendingDoubts.length === 0"
              style="display: flex; flex-direction: column; align-items: center; padding: 48px 0; gap: 12px;"
            >
              <Check :size="36" color="#009957" />
              <p
                style="font-family: Inter, sans-serif; font-weight: 500; font-size: 15px; color: #BDBABA; margin: 0;"
              >
                Todas as dúvidas foram revistas!
              </p>
            </div>

            <!-- CSS Grid — auto-fills columns at ≥280px each -->
            <div
              v-else
              style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;"
            >
              <!-- Square-ish card: flex-column, top text + bottom actions -->
              <div
                v-for="doubt in pendingDoubts"
                :key="doubt.id"
                style="border: 1px solid #E5E5E5; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 16px; justify-content: space-between; background: #ffffff;"
              >
                <!-- Top: theme + freq badge + example -->
                <div>
                  <div
                    style="display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;"
                  >
                    <span
                      style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #1E1E1E; flex: 1; min-width: 0;"
                    >
                      {{ doubt.theme }}
                    </span>
                    <span
                      style="font-family: Inter, sans-serif; font-size: 11px; background: #F5F5F5; padding: 2px 6px; border-radius: 4px; color: #656966; white-space: nowrap; flex-shrink: 0;"
                    >
                      {{ doubt.freq }} perguntas
                    </span>
                  </div>
                  <p
                    style="font-family: Inter, sans-serif; margin: 0; margin-top: 8px; font-size: 12px; color: #9E9E9E; font-style: italic; line-height: 1.4;"
                  >
                    Exemplo: "{{ doubt.exampleQuestion }}"
                  </p>
                </div>

                <!-- Bottom: action buttons, full-width split -->
                <div style="display: flex; gap: 8px; margin-top: auto;">
                  <button
                    style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 0; border-radius: 8px; border: 1px solid #E5E5E5; background: #ffffff; color: #E53935; font-family: Inter, sans-serif; font-size: 12px; font-weight: 600; cursor: pointer;"
                    @click="handleCurateDoubt(doubt.id, 'rejected')"
                  >
                    <X :size="14" color="#E53935" />
                    Ignorar
                  </button>
                  <button
                    style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 0; border-radius: 8px; border: none; background: #009957; color: #ffffff; font-family: Inter, sans-serif; font-size: 12px; font-weight: 600; cursor: pointer;"
                    @click="handleCurateDoubt(doubt.id, 'approved')"
                  >
                    <Check :size="14" color="#ffffff" />
                    Útil
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </RoleGuard>
</template>
