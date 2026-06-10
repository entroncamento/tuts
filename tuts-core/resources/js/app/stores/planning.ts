import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Plan } from '@/app/types'

// ─── Pattern: React PlanningContext + useState → Pinia setup store ────────────
//
// React's PlanningContext wrapped useState<Plan[]> in a Provider rendered at
// the app root (App.tsx). Any page calling usePlanningContext() received
// { plans, addPlan, deletePlan } from the nearest Provider ancestor.
//
// Vue replacement: a Pinia setup store. The `plans` ref is the direct equivalent
// of the React useState array; addPlan/deletePlan are plain functions that mutate
// the ref — identical business logic, no change in data-flow semantics.
//
// Why Pinia over provide/inject:
//   - PlanningProvider sat at the app root in React — it was global scope by
//     design, not a feature-scoped provider.
//   - At minimum MyPlansPage and PlanningPage both read from it; future pages
//     may also read/write plans.
//   - Pinia stores persist across route changes automatically. A provide/inject
//     at App.vue would require manual provide() calls and an injectionKey, with
//     no additional benefit for global state.

// ─── Seed data ────────────────────────────────────────────────────────────────
// Identical to SEED_PLANS in the React PlanningContext.tsx.
// Ensures the list page is never empty on first load.
const SEED_PLANS: Plan[] = [
  {
    id:        'seed-plan-1',
    title:     'Plano Intensivo — Exame Redes',
    subject:   'Redes de Computadores',
    aiSummary: 'Foco intensivo na resolução de exames e revisão do Módulo 3. Inclui 3 blocos diários de estudo e exercícios práticos de configuração de routers.',
    duration:  'Ocupa 5 dias',
    dateRange: 'de 28/04 a 02/05',
    messages: [
      {
        id:   'sm1',
        type: 'incoming',
        text: 'Olá Maria! 👋 Como posso ajudar a organizar a tua semana? Reparei que tens Exame de Redes na sexta-feira.',
        time: '10:00',
      },
      {
        id:   'sm2',
        type: 'outgoing',
        text: 'Quero criar um plano intensivo para o exame de Redes!',
        time: '10:01',
      },
      {
        id:   'sm3',
        type: 'incoming',
        text: 'Perfeito! Com base na tua disponibilidade, sugiro 2h de revisão na quarta e 1h de exercícios práticos na quinta à tarde. Vejo que tens os blocos de manhã livres na segunda e terça.',
        time: '10:01',
      },
      {
        id:   'sm4',
        type: 'outgoing',
        text: 'organizar semana',
        time: '10:03',
      },
      {
        id:   'sm5',
        type: 'incoming',
        text: 'Para organizar a semana, começa pelas matérias mais exigentes nas manhãs. Vejo que tens tempo livre na terça e quinta de tarde.',
        time: '10:03',
      },
    ],
    goals: [
      { id: 'sg1', text: 'Estudar Algoritmo de Dijkstra',    completed: true  },
      { id: 'sg2', text: 'Completar ficha prática de Redes', completed: false },
      { id: 'sg3', text: 'Rever Módulo 2 — Grafos',          completed: false },
      { id: 'sg4', text: 'Fazer exercícios de MD (cap. 5)',  completed: false },
    ],
  },
  {
    id:        'seed-plan-2',
    title:     'Revisão Semanal — Matemática',
    subject:   'Matemática Discreta',
    aiSummary: 'Revisão de grafos, combinatória e lógica proposicional com exercícios práticos do manual. Estratégia baseada em repetição espaçada.',
    duration:  'Ocupa 3 dias',
    dateRange: 'de 26/04 a 28/04',
    messages: [
      {
        id:   'sm6',
        type: 'incoming',
        text: 'Olá! Estou aqui para ajudar com Matemática Discreta. Queres focar em grafos ou em combinatória?',
        time: '09:00',
      },
      {
        id:   'sm7',
        type: 'outgoing',
        text: 'definir objetivos',
        time: '09:01',
      },
      {
        id:   'sm8',
        type: 'incoming',
        text: 'Recomendo 2 a 3 objetivos claros por semana. O que precisas consolidar antes do exame de Redes na sexta?',
        time: '09:01',
      },
    ],
    goals: [
      { id: 'sg5', text: 'Resolver exercícios do cap. 3', completed: true  },
      { id: 'sg6', text: 'Estudar lógica proposicional',   completed: true  },
      { id: 'sg7', text: 'Revisão de combinatória',        completed: false },
    ],
  },
]

// ─── Store ────────────────────────────────────────────────────────────────────
export const usePlanningStore = defineStore('planning', () => {
  const plans = ref<Plan[]>(SEED_PLANS)

  // Prepend so newest plan appears first — mirrors React addPlan behaviour
  function addPlan(plan: Plan) {
    plans.value = [plan, ...plans.value]
  }

  function deletePlan(id: string) {
    plans.value = plans.value.filter(p => p.id !== id)
  }

  return { plans, addPlan, deletePlan }
})
