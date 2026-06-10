import { onMounted, onUnmounted } from 'vue'
import { useShell } from '@/app/composables/useShell'

export function useShellHandler(handler: (text: string) => void): void {
  const { registerChatSendHandler, unregisterChatSendHandler } = useShell()
  onMounted(() => registerChatSendHandler(handler))
  onUnmounted(() => unregisterChatSendHandler(handler))
}
