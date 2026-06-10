import { defineStore } from 'pinia'
import { ref } from 'vue'

// ─── Pattern: React ShellContext + useRef/useCallback → Pinia setup store ────
//
// React's ShellContext held two mutable refs (handlerRef, focusRef) wrapped in
// useCallback-stabilised functions and broadcast down the tree via Context.
// Components registered/unregistered handlers in useEffect cleanup pairs.
//
// Vue replacement: the same two nullable function refs live in a Pinia store.
// Because Pinia stores are singletons, any component calling useShellStore()
// gets the same ref — no Provider/Consumer tree required. The register/dispatch
// pattern is identical; Vue's reactivity system is not involved (we intentionally
// do NOT make the handler functions themselves reactive — we just store them).
//
// Why Pinia over provide/inject:
//   - BottomChatInput (deep in the layout) registers handlers; ChatHubPage and
//     other pages dispatch into them. These are never in the same component
//     subtree, so provide/inject would need to be at the App root — which is
//     exactly what Pinia already does, but with better ergonomics.
//   - No need for an injectionKey symbol or a provider component.

export const useShellStore = defineStore('shell', () => {
  const chatSendHandler = ref<((msg: string) => void) | null>(null)
  const focusHandler    = ref<(() => void) | null>(null)

  function registerChatSendHandler(fn: (msg: string) => void) {
    chatSendHandler.value = fn
  }

  function unregisterChatSendHandler() {
    chatSendHandler.value = null
  }

  function dispatchChatSend(msg: string) {
    chatSendHandler.value?.(msg)
  }

  function registerFocusHandler(fn: () => void) {
    focusHandler.value = fn
  }

  function unregisterFocusHandler() {
    focusHandler.value = null
  }

  function focusChatInput() {
    focusHandler.value?.()
  }

  return {
    registerChatSendHandler,
    unregisterChatSendHandler,
    dispatchChatSend,
    registerFocusHandler,
    unregisterFocusHandler,
    focusChatInput,
  }
})
