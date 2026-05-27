<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Search, MessageCircle, MoreVertical, EyeOff } from '@lucide/vue'
import UCCard from '@/app/components/UCCard.vue'
import { fetchMySubjects } from '@/app/services/subjects'
import { apiFetch } from '@/app/services/api'
import { UC_LIST, type UCData } from '@/app/data/ucData'

type TabType = 'ucs' | 'spaces' | 'conversations'

interface ChatSummary {
  chat_id: number
  subject_id?: number | null
  nome_uc: string | null
  title: string | null
  updated_at: string
}

const router = useRouter()
const route = useRoute()

const activeTab = ref<TabType>('ucs')
const searchQuery = ref('')
const ucs = ref<UCData[]>(UC_LIST)
const chats = ref<ChatSummary[]>([])
const loading = ref(false)

watch(
  () => route.query.tab,
  (tabParam) => {
    if (tabParam === 'espacos') activeTab.value = 'spaces'
    else if (tabParam === 'conversas') activeTab.value = 'conversations'
    else activeTab.value = 'ucs'
  },
  { immediate: true },
)

watch(activeTab, () => {
  searchQuery.value = ''
})

const filteredUcs = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return ucs.value
  return ucs.value.filter((uc) => uc.name.toLowerCase().includes(q))
})

const filteredChats = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return chats.value
  return chats.value.filter((chat) =>
    `${chat.title ?? ''} ${chat.nome_uc ?? ''}`.toLowerCase().includes(q),
  )
})

async function loadData() {
  loading.value = true

  try {
    const [subjects, chatResponse] = await Promise.all([
      fetchMySubjects(),
      apiFetch<{ status: string; chats: ChatSummary[] }>('/api/chat/ucs').catch(() => ({ status: 'erro', chats: [] })),
    ])

    ucs.value = subjects
    chats.value = chatResponse.chats ?? []
  } finally {
    loading.value = false
  }
}

function openChat(chat: ChatSummary) {
  router.push({
    name: 'chat',
    query: { chat_id: String(chat.chat_id), uc: chat.nome_uc ?? '' },
  })
}

onMounted(loadData)
</script>

<template>
  <div style="height: 100%; overflow-y: auto; background: #ffffff; padding-bottom: 110px;">
    <div style="max-width: 1200px; margin: 0 auto; padding: 32px 24px;">
      <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 28px;">
        <div>
          <h1 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 28px; color: #1E1E1E; margin: 0 0 6px;">
            UC's & Espaços
          </h1>
          <p style="font-family: Inter, sans-serif; font-size: 13px; color: #9E9E9E; margin: 0;">
            UCs reais vêm do Laravel. Espaços continuam locais até existir backend próprio.
          </p>
        </div>
      </div>

      <div style="display: flex; gap: 12px; margin-bottom: 24px; border-bottom: 1px solid #E5E5E5;">
        <button
          v-for="tab in [{ id: 'ucs', label: 'UCs' }, { id: 'conversations', label: 'Conversas' }, { id: 'spaces', label: 'Espaços' }]"
          :key="tab.id"
          :style="{
            background: 'none',
            border: 'none',
            borderBottom: activeTab === tab.id ? '2px solid #009957' : '2px solid transparent',
            color: activeTab === tab.id ? '#009957' : '#9E9E9E',
            fontFamily: 'Inter, sans-serif',
            fontWeight: activeTab === tab.id ? 700 : 500,
            fontSize: '14px',
            padding: '0 0 12px',
            marginRight: '20px',
            cursor: 'pointer',
          }"
          @click="activeTab = tab.id as TabType"
        >
          {{ tab.label }}
        </button>
      </div>

      <div style="position: relative; margin-bottom: 24px;">
        <Search :size="16" color="#9E9E9E" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%);" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Pesquisar..."
          style="width: 100%; box-sizing: border-box; border: 1px solid #E5E5E5; border-radius: 12px; padding: 12px 14px 12px 40px; font-family: Inter, sans-serif; outline: none;"
        />
      </div>

      <p v-if="loading" style="font-family: Inter, sans-serif; color: #9E9E9E;">
        A carregar dados reais...
      </p>

      <template v-else>
        <div v-if="activeTab === 'ucs'" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px;">
          <UCCard
            v-for="uc in filteredUcs"
            :key="uc.id"
            v-bind="uc"
          />
        </div>

        <div v-else-if="activeTab === 'conversations'" style="display: flex; flex-direction: column; gap: 12px;">
          <p v-if="filteredChats.length === 0" style="font-family: Inter, sans-serif; color: #9E9E9E;">
            Ainda não tens conversas guardadas.
          </p>

          <button
            v-for="chat in filteredChats"
            :key="chat.chat_id"
            style="display: flex; align-items: center; gap: 14px; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 14px; padding: 16px; cursor: pointer; text-align: left;"
            @click="openChat(chat)"
          >
            <div style="width: 40px; height: 40px; border-radius: 10px; background: #F5F5F5; display: flex; align-items: center; justify-content: center;">
              <MessageCircle :size="18" color="#009957" />
            </div>
            <div style="flex: 1;">
              <p style="font-family: Inter, sans-serif; font-weight: 700; font-size: 14px; color: #1E1E1E; margin: 0 0 4px;">
                {{ chat.title || 'Conversa sem título' }}
              </p>
              <p style="font-family: Inter, sans-serif; font-size: 12px; color: #9E9E9E; margin: 0;">
                {{ chat.nome_uc || 'Sem UC' }} · {{ new Date(chat.updated_at).toLocaleString('pt-PT') }}
              </p>
            </div>
            <MoreVertical :size="16" color="#BDBABA" />
          </button>
        </div>

        <div v-else style="border: 1px dashed #E5E5E5; border-radius: 16px; padding: 32px; text-align: center;">
          <EyeOff :size="28" color="#BDBABA" />
          <p style="font-family: Inter, sans-serif; color: #656966; margin-bottom: 4px;">
            Espaços ainda estão em modo frontend.
          </p>
          <p style="font-family: Inter, sans-serif; color: #BDBABA; font-size: 13px; margin: 0;">
            Para ficarem reais, falta criar tabelas/endpoints para espaços, ficheiros pessoais e associações.
          </p>
        </div>
      </template>
    </div>
  </div>
</template>
