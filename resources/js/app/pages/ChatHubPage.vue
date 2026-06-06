<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { BookOpen, Folder, Send } from '@lucide/vue'

import MarkdownMessage from '@/app/components/MarkdownMessage.vue'
import CitationPdfModal from '@/app/components/CitationPdfModal.vue'
import { fetchMySubjects } from '@/app/services/subjects'
import { fetchSpaces, type StudySpace } from '@/app/services/spaces'
import { apiFetch } from '@/app/services/api'
import { type UCData, UC_LIST } from '@/app/data/ucData'
import { useTutsChat, type TutsChatMessage } from '@/app/composables/useTutsChat'

const route = useRoute()
const router = useRouter()

type ChatContext = 'uc' | 'space' | 'temporary'

const ucs = ref<UCData[]>(UC_LIST)
const spaces = ref<StudySpace[]>([])
const selectedContext = ref<ChatContext>('uc')
const selectedUcId = ref<string>('')
const selectedSpaceId = ref<string>('')
const selectedFolderId = ref<string>('')
const inputValue = ref('')
const chatEndRef = ref<HTMLDivElement | null>(null)
const loadingHistory = ref(false)
const loadingContext = ref(false)

const citationModalOpen = ref(false)
const citationFile = ref('')
const citationPage = ref(1)

const {
  messages,
  isStreaming,
  error,
  chatId,
  lastStatus,
  sendMessage,
  setChatId,
  setMessages,
  clearMessages,
} = useTutsChat()

const selectedUc = computed(() => ucs.value.find((uc) => uc.id === selectedUcId.value) ?? null)
const selectedSpace = computed(() => spaces.value.find((space) => String(space.id) === selectedSpaceId.value) ?? null)

const contextLabel = computed(() => {
  if (selectedContext.value === 'uc') return selectedUc.value?.name ?? 'UC'
  if (selectedContext.value === 'space') return selectedSpace.value?.name ?? 'Espaço'
  return 'Conversa temporária'
})

const canSend = computed(() => {
  if (isStreaming.value || !inputValue.value.trim()) return false
  if (selectedContext.value === 'uc') return !!selectedUc.value
  if (selectedContext.value === 'space') return !!selectedSpace.value
  return true
})

function convertBackendMessage(message: {
  id: number
  role: 'user' | 'ai'
  content: string
}): TutsChatMessage {
  return {
    id: String(message.id),
    role: message.role === 'ai' ? 'assistant' : 'user',
    content: message.content,
  }
}

function openCitation(payload: { file: string; page: number }) {
  citationFile.value = payload.file
  citationPage.value = payload.page
  citationModalOpen.value = true
}

function closeCitation() {
  citationModalOpen.value = false
}

async function loadInitialState() {
  loadingContext.value = true

  try {
    const [subjects, spacesResponse] = await Promise.all([
      fetchMySubjects(),
      fetchSpaces().catch(() => []),
    ])

    ucs.value = subjects
    spaces.value = spacesResponse

    const queryContext = typeof route.query.context === 'string' ? route.query.context : ''
    const queryUc = typeof route.query.uc === 'string' ? route.query.uc : ''
    const querySpaceId = typeof route.query.space_id === 'string' ? route.query.space_id : ''
    const queryFolderId = typeof route.query.folder_id === 'string' ? route.query.folder_id : ''
    const queryChatId = typeof route.query.chat_id === 'string' ? Number(route.query.chat_id) : null

    if (queryContext === 'space') {
      selectedContext.value = 'space'
      selectedSpaceId.value = querySpaceId
      selectedFolderId.value = queryFolderId
    } else if (queryContext === 'temporary') {
      selectedContext.value = 'temporary'
    } else {
      selectedContext.value = 'uc'
    }

    if (queryUc) {
      const match = ucs.value.find((uc) => uc.name === queryUc)
      if (match) selectedUcId.value = match.id
    }

    if (!selectedUcId.value && ucs.value.length > 0) {
      selectedUcId.value = ucs.value[0].id
    }

    if (!selectedSpaceId.value && spaces.value.length > 0 && selectedContext.value === 'space') {
      selectedSpaceId.value = String(spaces.value[0].id)
    }

    if (queryChatId && Number.isFinite(queryChatId)) {
      await loadChat(queryChatId)
    }
  } finally {
    loadingContext.value = false
  }
}

async function loadChat(id: number) {
  loadingHistory.value = true

  try {
    const response = await apiFetch<{
      status: string
      chat_id: number
      titulo: string
      context_type?: ChatContext
      subject_name?: string | null
      space_id?: number | null
      space_name?: string | null
      folder_id?: number | null
      folder_name?: string | null
      mensagens: Array<{
        id: number
        role: 'user' | 'ai'
        content: string
      }>
    }>(`/api/chat/${id}`)

    setChatId(response.chat_id)
    setMessages(response.mensagens.map(convertBackendMessage))

    if (response.context_type === 'space' && response.space_id) {
      selectedContext.value = 'space'
      selectedSpaceId.value = String(response.space_id)
      selectedFolderId.value = response.folder_id ? String(response.folder_id) : ''
    } else if (response.context_type === 'temporary') {
      selectedContext.value = 'temporary'
    } else if (response.subject_name) {
      selectedContext.value = 'uc'
      const match = ucs.value.find((uc) => uc.name === response.subject_name)
      if (match) selectedUcId.value = match.id
    }
  } finally {
    loadingHistory.value = false
  }
}

async function handleSend() {
  const text = inputValue.value.trim()

  if (!text || isStreaming.value || !canSend.value) return

  inputValue.value = ''

  await sendMessage({
    message: text,
    ucName: selectedContext.value === 'uc' ? selectedUc.value?.name ?? null : null,
    contextType: selectedContext.value,
    spaceId: selectedContext.value === 'space' ? selectedSpace.value?.id ?? null : null,
    folderId: selectedContext.value === 'space' ? selectedFolderId.value || null : null,
  })
}

async function newConversation() {
  clearMessages()
  setChatId(null)
  inputValue.value = ''

  const query: Record<string, string> = {}

  if (selectedContext.value === 'uc' && selectedUc.value?.name) {
    query.context = 'uc'
    query.uc = selectedUc.value.name
  }

  if (selectedContext.value === 'space' && selectedSpace.value) {
    query.context = 'space'
    query.space_id = String(selectedSpace.value.id)
    query.space = selectedSpace.value.name
    if (selectedFolderId.value) query.folder_id = selectedFolderId.value
  }

  if (selectedContext.value === 'temporary') {
    query.context = 'temporary'
  }

  await router.replace({ path: '/chat', query })
}

watch(chatId, async (newChatId) => {
  if (!newChatId) return

  const currentChatId = typeof route.query.chat_id === 'string' ? Number(route.query.chat_id) : null
  if (currentChatId === Number(newChatId)) return

  const query: Record<string, string> = { chat_id: String(newChatId), context: selectedContext.value }

  if (selectedContext.value === 'uc' && selectedUc.value?.name) {
    query.uc = selectedUc.value.name
  }

  if (selectedContext.value === 'space' && selectedSpace.value) {
    query.space_id = String(selectedSpace.value.id)
    query.space = selectedSpace.value.name
    if (selectedFolderId.value) query.folder_id = selectedFolderId.value
  }

  await router.replace({ path: '/chat', query })
})

watch(
  messages,
  async () => {
    await nextTick()
    chatEndRef.value?.scrollIntoView({ behavior: 'smooth' })
  },
  { deep: true },
)

onMounted(loadInitialState)
</script>

<template>
  <div style="height: 100%; display: flex; flex-direction: column; background: #ffffff; font-family: Inter, sans-serif;">
    <div style="max-width: 920px; width: 100%; margin: 0 auto; padding: 24px 24px 0; box-sizing: border-box;">
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px;">
        <div>
          <h1 style="font-size: 24px; font-weight: 700; color: #1e1e1e; margin: 0 0 4px;">
            Chat TUT'S
          </h1>

          <p style="font-size: 13px; color: #9e9e9e; margin: 0;">
            Contexto atual: {{ contextLabel }}
          </p>
        </div>

        <button
          style="border: 1px solid #e5e5e5; background: #ffffff; border-radius: 10px; padding: 9px 14px; cursor: pointer; color: #656966; font-weight: 600;"
          @click="newConversation"
        >
          Nova conversa
        </button>
      </div>

      <div style="display: grid; grid-template-columns: 180px 1fr; gap: 12px; align-items: center; margin-bottom: 18px;">
        <select
          v-model="selectedContext"
          style="border: 1px solid #e5e5e5; border-radius: 10px; padding: 10px 12px; font-family: Inter, sans-serif; outline: none;"
          @change="newConversation"
        >
          <option value="uc">UC</option>
          <option value="space">Espaço</option>
          <option value="temporary">Temporária</option>
        </select>

        <div v-if="selectedContext === 'uc'" style="display: flex; gap: 10px; align-items: center;">
          <BookOpen :size="17" color="#009957" />
          <select
            v-model="selectedUcId"
            style="flex: 1; border: 1px solid #e5e5e5; border-radius: 10px; padding: 10px 12px; font-family: Inter, sans-serif; outline: none;"
          >
            <option value="" disabled>Escolhe uma UC</option>
            <option v-for="uc in ucs" :key="uc.id" :value="uc.id">
              {{ uc.name }}
            </option>
          </select>
        </div>

        <div v-else-if="selectedContext === 'space'" style="display: flex; gap: 10px; align-items: center;">
          <Folder :size="17" color="#009957" />
          <select
            v-model="selectedSpaceId"
            style="flex: 1; border: 1px solid #e5e5e5; border-radius: 10px; padding: 10px 12px; font-family: Inter, sans-serif; outline: none;"
          >
            <option value="" disabled>Escolhe um Espaço</option>
            <option v-for="space in spaces" :key="space.id" :value="String(space.id)">
              {{ space.name }}
            </option>
          </select>
        </div>

        <div v-else style="display: flex; align-items: center; gap: 10px; border: 1px solid #E5E5E5; border-radius: 10px; padding: 10px 12px; color: #656966; font-size: 13px;">
          Conversa solta sem UC ou Espaço. Podes anexar a um Espaço mais tarde.
        </div>
      </div>
    </div>

    <div style="flex: 1; overflow-y: auto;">
      <div style="max-width: 920px; margin: 0 auto; padding: 20px 24px 130px; display: flex; flex-direction: column; gap: 18px; box-sizing: border-box;">
        <div
          v-if="messages.length === 0 && !loadingHistory"
          style="min-height: 360px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;"
        >
          <div style="width: 54px; height: 54px; border-radius: 16px; background: rgba(0,153,87,0.08); display: flex; align-items: center; justify-content: center; margin-bottom: 18px;">
            <BookOpen v-if="selectedContext === 'uc'" :size="26" color="#009957" />
            <Folder v-else :size="26" color="#009957" />
          </div>

          <h2 style="font-size: 22px; color: #1e1e1e; margin: 0 0 8px;">
            Estudar com o TUT'S
          </h2>

          <p style="font-size: 14px; color: #9e9e9e; margin: 0; max-width: 460px; line-height: 1.5;">
            Escolhe uma UC, um Espaço ou começa uma conversa temporária. A conversa fica guardada no contexto certo.
          </p>
        </div>

        <p v-if="loadingContext || loadingHistory" style="color: #9e9e9e;">
          A carregar...
        </p>

        <div
          v-for="message in messages"
          :key="message.id"
          :style="{
            alignSelf: message.role === 'user' ? 'flex-end' : 'flex-start',
            maxWidth: '82%',
            display: 'flex',
            flexDirection: 'column',
            gap: '4px',
          }"
        >
          <div
            :style="{
              background: message.role === 'user' ? '#1E1E1E' : '#F5F5F5',
              color: message.role === 'user' ? '#ffffff' : '#1E1E1E',
              borderRadius: message.role === 'user' ? '16px 16px 4px 16px' : '16px 16px 16px 4px',
              padding: '14px 18px',
              lineHeight: 1.65,
              fontSize: '14px',
            }"
          >
            <MarkdownMessage
              :text="message.content || (message.loading ? 'A pensar...' : '')"
              :tone="message.role"
              @open-citation="openCitation"
            />
          </div>
        </div>

        <div v-if="lastStatus" style="align-self: flex-start; font-size: 12px; color: #9e9e9e;">
          {{ lastStatus }}
        </div>

        <p v-if="error" style="color: #e53935; font-size: 13px;">
          {{ error }}
        </p>

        <div ref="chatEndRef" />
      </div>
    </div>

    <div style="position: fixed; left: 80px; right: 0; bottom: 0; background: linear-gradient(to top, #ffffff 70%, transparent); padding: 18px 24px 22px;">
      <div style="max-width: 920px; margin: 0 auto;">
        <div style="display: flex; align-items: center; gap: 12px; border: 1px solid #e5e5e5; border-radius: 999px; padding: 8px 8px 8px 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.08); background: #ffffff;">
          <input
            v-model="inputValue"
            type="text"
            :disabled="isStreaming || !canSend && !inputValue.trim()"
            placeholder="Faz qualquer pergunta."
            style="flex: 1; border: none; outline: none; background: transparent; font-family: Inter, sans-serif; font-size: 14px;"
            @keydown.enter.prevent="handleSend"
          />

          <button
            :disabled="!canSend"
            :style="{
              width: '42px',
              height: '42px',
              borderRadius: '50%',
              border: 'none',
              background: '#009957',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              cursor: canSend ? 'pointer' : 'not-allowed',
              opacity: canSend ? 1 : 0.5,
            }"
            @click="handleSend"
          >
            <Send :size="17" color="#ffffff" />
          </button>
        </div>
      </div>
    </div>

    <CitationPdfModal
      :open="citationModalOpen"
      :file="citationFile"
      :page="citationPage"
      @close="closeCitation"
    />
  </div>
</template>
