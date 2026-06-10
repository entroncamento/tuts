<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, BookOpen, MessageCircle, Plus, Send } from '@lucide/vue'
import { fetchMySubjects } from '@/app/services/subjects'
import { apiFetch } from '@/app/services/api'
import { UC_LIST, mapUCsById, type UCData } from '@/app/data/ucData'
import { useTutsChat, type TutsChatMessage } from '@/app/composables/useTutsChat'

interface ChatSummary {
  chat_id: number
  nome_uc: string | null
  title: string | null
  updated_at: string
}

const route = useRoute()
const router = useRouter()

const ucs = ref<UCData[]>(UC_LIST)
const chats = ref<ChatSummary[]>([])
const inputValue = ref('')
const chatEndRef = ref<HTMLDivElement | null>(null)
const isChatActive = ref(false)

const {
  messages,
  isStreaming,
  error,
  lastStatus,
  sendMessage,
  setChatId,
  setMessages,
  clearMessages,
} = useTutsChat()

const ucMap = computed(() => mapUCsById(ucs.value))
const ucId = computed(() => route.params.id as string)
const uc = computed(() => ucMap.value[ucId.value] ?? null)

const ucChats = computed(() =>
  chats.value.filter((chat) => chat.nome_uc === uc.value?.name),
)

function convertBackendMessage(message: { id: number; role: 'user' | 'ai'; content: string }): TutsChatMessage {
  return {
    id: String(message.id),
    role: message.role === 'ai' ? 'assistant' : 'user',
    content: message.content,
  }
}

async function loadData() {
  ucs.value = await fetchMySubjects()

  const chatResponse = await apiFetch<{ status: string; chats: ChatSummary[] }>('/api/chat/ucs')
    .catch(() => ({ status: 'erro', chats: [] }))

  chats.value = chatResponse.chats ?? []
}

async function openChat(chatId: number) {
  const response = await apiFetch<{
    status: string
    chat_id: number
    titulo: string
    mensagens: Array<{ id: number; role: 'user' | 'ai'; content: string }>
  }>(`/api/chat/${chatId}`)

  setChatId(response.chat_id)
  setMessages(response.mensagens.map(convertBackendMessage))
  isChatActive.value = true
}

function startNewChat() {
  clearMessages()
  isChatActive.value = true
}

async function handleSend() {
  const text = inputValue.value.trim()
  if (!text || !uc.value) return

  inputValue.value = ''

  await sendMessage({
    message: text,
    ucName: uc.value.name,
  })

  await loadData()
}

watch(messages, async () => {
  await nextTick()
  chatEndRef.value?.scrollIntoView({ behavior: 'smooth' })
}, { deep: true })

onMounted(loadData)
</script>

<template>
  <div v-if="!uc" style="height: 100%; display: flex; align-items: center; justify-content: center;">
    <p style="font-family: Inter, sans-serif; color: #9E9E9E;">UC não encontrada.</p>
  </div>

  <div v-else-if="isChatActive" style="height: 100%; display: flex; flex-direction: column; overflow: hidden; font-family: Inter, sans-serif;">
    <div style="padding: 18px 24px 0;">
      <button
        style="display: flex; align-items: center; gap: 7px; border: none; background: none; color: #9E9E9E; cursor: pointer; font-weight: 600;"
        @click="isChatActive = false"
      >
        <ArrowLeft :size="15" />
        Voltar ao Hub da UC
      </button>
    </div>

    <div style="max-width: 920px; width: 100%; margin: 0 auto; padding: 20px 24px 130px; overflow-y: auto; flex: 1; box-sizing: border-box;">
      <div style="border-bottom: 1px solid #F0F0F0; padding-bottom: 14px; margin-bottom: 24px;">
        <p style="font-weight: 700; color: #1E1E1E; margin: 0;">Assistente IA — {{ uc.shortCode }}</p>
        <p style="font-size: 12px; color: #9E9E9E; margin: 3px 0 0;">{{ uc.name }}</p>
      </div>

      <div style="display: flex; flex-direction: column; gap: 14px;">
        <div
          v-for="message in messages"
          :key="message.id"
          :style="{
            alignSelf: message.role === 'user' ? 'flex-end' : 'flex-start',
            maxWidth: '78%',
            background: message.role === 'user' ? '#1E1E1E' : '#F5F5F5',
            color: message.role === 'user' ? '#ffffff' : '#1E1E1E',
            borderRadius: message.role === 'user' ? '16px 16px 4px 16px' : '16px 16px 16px 4px',
            padding: '13px 17px',
            whiteSpace: 'pre-wrap',
            lineHeight: 1.6,
            fontSize: '14px',
          }"
        >
          {{ message.content || (message.loading ? 'A pensar...' : '') }}
        </div>

        <p v-if="lastStatus" style="font-size: 12px; color: #9E9E9E; margin: 0;">
          {{ lastStatus }}
        </p>

        <p v-if="error" style="font-size: 13px; color: #E53935; margin: 0;">
          {{ error }}
        </p>

        <div ref="chatEndRef" />
      </div>
    </div>

    <div style="position: fixed; left: 80px; right: 0; bottom: 0; background: linear-gradient(to top, #ffffff 70%, transparent); padding: 18px 24px 22px;">
      <div style="max-width: 920px; margin: 0 auto;">
        <div style="display: flex; align-items: center; gap: 12px; border: 1px solid #E5E5E5; border-radius: 999px; padding: 8px 8px 8px 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.08); background: #ffffff;">
          <input
            v-model="inputValue"
            type="text"
            :disabled="isStreaming"
            placeholder="Escreve a tua pergunta sobre esta UC."
            style="flex: 1; border: none; outline: none; background: transparent; font-family: Inter, sans-serif; font-size: 14px;"
            @keydown.enter.prevent="handleSend"
          />
          <button
            :disabled="isStreaming || !inputValue.trim()"
            style="width: 42px; height: 42px; border-radius: 50%; border: none; background: #009957; display: flex; align-items: center; justify-content: center; cursor: pointer;"
            @click="handleSend"
          >
            <Send :size="17" color="#ffffff" />
          </button>
        </div>
      </div>
    </div>
  </div>

  <div v-else style="height: 100%; overflow-y: auto; padding-bottom: 120px; font-family: Inter, sans-serif;">
    <div style="max-width: 1200px; margin: 0 auto; padding: 32px;">
      <div style="height: 160px; border-radius: 16px; position: relative; overflow: hidden; margin-bottom: 24px;">
        <div :style="{ width: '100%', height: '100%', background: uc.cover }" />
        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.45);" />

        <button
          style="position: absolute; top: 16px; left: 20px; display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); border-radius: 8px; padding: 6px 12px; cursor: pointer; color: #ffffff;"
          @click="router.push({ name: 'ucs' })"
        >
          <ArrowLeft :size="13" />
          UC's e Espaços
        </button>

        <div style="position: absolute; bottom: 0; left: 0; padding: 24px;">
          <h1 style="font-weight: 700; font-size: 28px; color: #ffffff; margin: 0; line-height: 1.2;">
            {{ uc.name }}
          </h1>
          <p style="font-size: 14px; color: rgba(255,255,255,0.82); margin: 6px 0 0;">
            {{ uc.teacher }} · {{ uc.year }}
          </p>
        </div>
      </div>

      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px;">
        <h2 style="font-weight: 700; font-size: 18px; color: #1E1E1E; margin: 0;">
          Histórico de conversas
        </h2>

        <button
          style="display: flex; align-items: center; gap: 7px; border: none; background: #009957; color: #ffffff; border-radius: 10px; padding: 10px 16px; cursor: pointer; font-weight: 700;"
          @click="startNewChat"
        >
          <Plus :size="15" />
          Nova conversa
        </button>
      </div>

      <div v-if="ucChats.length === 0" style="border: 1px dashed #E5E5E5; border-radius: 16px; padding: 28px; text-align: center;">
        <BookOpen :size="26" color="#BDBABA" />
        <p style="color: #656966; margin-bottom: 4px;">Ainda não há conversas nesta UC.</p>
        <p style="color: #BDBABA; font-size: 13px; margin: 0;">Cria uma conversa para começar a estudar com o TUT'S.</p>
      </div>

      <div v-else style="display: flex; flex-direction: column; gap: 10px;">
        <button
          v-for="chat in ucChats"
          :key="chat.chat_id"
          style="display: flex; align-items: center; gap: 14px; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 16px; cursor: pointer; text-align: left;"
          @click="openChat(chat.chat_id)"
        >
          <div style="width: 36px; height: 36px; border-radius: 10px; background: #F5F5F5; display: flex; align-items: center; justify-content: center;">
            <MessageCircle :size="16" color="#009957" />
          </div>
          <div>
            <p style="font-weight: 700; font-size: 14px; color: #1E1E1E; margin: 0 0 4px;">{{ chat.title || 'Conversa sem título' }}</p>
            <p style="font-size: 12px; color: #9E9E9E; margin: 0;">{{ new Date(chat.updated_at).toLocaleString('pt-PT') }}</p>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
