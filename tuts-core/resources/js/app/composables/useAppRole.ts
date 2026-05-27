import { storeToRefs } from 'pinia'
import type { Ref } from 'vue'
import { useAppRoleStore } from '@/app/stores/appRole'
import type { AppRole } from '@/app/types'

// ─── Drop-in equivalent of React's useAppRole() hook ─────────────────────────
//
// Returns { role, setRole } — same API surface as the React hook.
//
// storeToRefs() is required here: Pinia setup stores unwrap refs when accessed
// via the store proxy (store.role gives the raw string, not the Ref). To keep
// the returned `role` reactive when destructured by callers, we extract it with
// storeToRefs() which preserves the ref wrapper.
//
// Usage:
//   const { role, setRole } = useAppRole()
//   // role is Ref<'student'|'teacher'> — reactive in templates and watchers

export function useAppRole(): { role: Ref<AppRole>; setRole: (next: AppRole) => void } {
  const store        = useAppRoleStore()
  const { role }     = storeToRefs(store)
  return { role, setRole: store.setRole }
}
