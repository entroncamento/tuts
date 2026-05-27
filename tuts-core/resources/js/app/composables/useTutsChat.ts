import { ref } from 'vue'
import { streamChatMessage, type ChatPreference } from '@/app/services/chat'

export type TutsChatMessage = {
  id: string
  role: 'user' | 'assistant'
  content: string
  loading?: boolean
}

function makeId(): string {
  return `${Date.now()}-${Math.random().toString(16).slice(2)}`
}

export function useTutsChat() {
  const messages = ref<TutsChatMessage[]>([])
  const isStreaming = ref(false)
  const error = ref<string | null>(null)
  const chatId = ref<number | null>(null)
  const lastStatus = ref<string | null>(null)

  async function sendMessage(options: {
    message: string
    ucName: string
    chatId?: number | string | null
    image?: File | null
    preferencia?: ChatPreference
  }) {
    const cleanMessage = options.message.trim()

    if (!cleanMessage || isStreaming.value) return

    if (!options.ucName?.trim()) {
      error.value = 'Tens de escolher uma UC antes de perguntar ao TUT’S.'
      return
    }

    error.value = null
    lastStatus.value = null

    messages.value.push({
      id: makeId(),
      role: 'user',
      content: cleanMessage,
    })

    const assistantMessage: TutsChatMessage = {
      id: makeId(),
      role: 'assistant',
      content: '',
      loading: true,
    }

    messages.value.push(assistantMessage)

    isStreaming.value = true

    try {
      await streamChatMessage(
        {
          texto: cleanMessage,
          uc: options.ucName,
          chat_id: options.chatId ?? chatId.value,
          imagem: options.image ?? null,
          preferencia: options.preferencia ?? 'default',
        },
        {
          onChatId(nextChatId) {
            chatId.value = nextChatId
          },

          onStatus(status) {
            lastStatus.value = status
          },

          onChunk(chunk) {
            assistantMessage.content += chunk
          },

          onDone() {
            assistantMessage.loading = false
            isStreaming.value = false
          },

          onError(err) {
            assistantMessage.loading = false
            isStreaming.value = false
            error.value = err.message
          },
        },
      )
    } catch {
      assistantMessage.loading = false
      isStreaming.value = false
    }
  }

  function setChatId(nextChatId: number | null) {
    chatId.value = nextChatId
  }

  function setMessages(nextMessages: TutsChatMessage[]) {
    messages.value = nextMessages
  }

  function clearMessages() {
    messages.value = []
    chatId.value = null
    error.value = null
    lastStatus.value = null
  }

  return {
    messages,
    isStreaming,
    error,
    chatId,
    lastStatus,
    sendMessage,
    setChatId,
    setMessages,
    clearMessages,
  }
}
