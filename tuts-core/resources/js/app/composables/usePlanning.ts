import { storeToRefs } from 'pinia'
import type { Ref } from 'vue'
import { usePlanningStore } from '@/app/stores/planning'
import type { Plan } from '@/app/types'

// ─── Drop-in equivalent of React's usePlanningContext() hook ─────────────────
//
// Returns { plans, addPlan, deletePlan } — same API surface as the React hook.
//
// storeToRefs() keeps `plans` reactive when destructured. Without it, Vue's
// Pinia proxy unwraps the ref to a plain array snapshot.
//
// Usage:
//   const { plans, addPlan, deletePlan } = usePlanning()
//   // plans is Ref<Plan[]> — reactive in templates and computed properties

export function usePlanning(): { plans: Ref<Plan[]>; addPlan: (plan: Plan) => void; deletePlan: (id: string) => void } {
  const store       = usePlanningStore()
  const { plans }   = storeToRefs(store)
  return {
    plans,
    addPlan:    store.addPlan,
    deletePlan: store.deletePlan,
  }
}

// Re-export Plan type so import sites can write:
//   import { usePlanning, type Plan } from '@/app/composables/usePlanning'
// — mirrors how React pages imported Plan from PlanningContext.tsx
export type { Plan }
