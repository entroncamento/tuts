import { useShellStore } from '@/app/stores/shell'

// ─── Drop-in equivalent of React's useShell() hook ───────────────────────────
//
// Returns all six shell methods — same API surface as the React hook.
//
// No storeToRefs() needed: this composable only exposes functions, not reactive
// state. Functions are stable references on the store instance.
//
// Usage:
//   const { registerFocusHandler, unregisterFocusHandler, focusChatInput } = useShell()
//   const { registerChatSendHandler, unregisterChatSendHandler, dispatchChatSend } = useShell()

export function useShell(): {
  registerChatSendHandler:   (fn: (msg: string) => void) => void
  unregisterChatSendHandler: () => void
  dispatchChatSend:          (msg: string) => void
  registerFocusHandler:      (fn: () => void) => void
  unregisterFocusHandler:    () => void
  focusChatInput:            () => void
} {
  const store = useShellStore()
  return {
    registerChatSendHandler:   store.registerChatSendHandler,
    unregisterChatSendHandler: store.unregisterChatSendHandler,
    dispatchChatSend:          store.dispatchChatSend,
    registerFocusHandler:      store.registerFocusHandler,
    unregisterFocusHandler:    store.unregisterFocusHandler,
    focusChatInput:            store.focusChatInput,
  }
}
