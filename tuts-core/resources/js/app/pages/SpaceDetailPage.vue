<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft, MessageCircle, UploadCloud, FileText,
  Folder, ChevronUp, ChevronDown, Plus, Send, ChevronRight,
  X, Check,
} from '@lucide/vue'
import WidgetCard from '@/app/components/WidgetCard.vue'

// ─── Types ─────────────────────────────────────────────────────────────────────
interface FolderItem { id: string; name: string }
interface ConversationSummary { id: string; title: string; createdAt: string; folderId: string | null }
interface ChatMessage { id: string; type: 'incoming' | 'outgoing'; text: string; time: string }
interface PersonalFile { id: string; name: string; size: string }
interface SpaceData { name: string; desc: string; gradient: string }

type TabId      = 'conversas' | 'materiais'
type PickerTab  = 'meus' | 'ucs' | 'upload'
type UploadStep = 'dropzone' | 'destination'

// ─── Space map ─────────────────────────────────────────────────────────────────
const SPACE_MAP: Record<string, SpaceData> = {
  s1: { name: 'Exames 2026',   desc: 'Fichas e resumos para preparação',     gradient: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' },
  s2: { name: 'Projeto Final', desc: 'Documentação e recursos do projeto',    gradient: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' },
  s3: { name: 'Redes & TCP/IP',desc: 'Materiais de Redes de Computadores',    gradient: 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)' },
}
const FALLBACK_SPACE: SpaceData = { name: 'Espaço de Estudo', desc: 'O teu espaço personalizado de estudo', gradient: 'linear-gradient(135deg, #43e97b 0%, #009957 100%)' }

// ─── Mock data ─────────────────────────────────────────────────────────────────
const MOCK_DRIVE_FILES: PersonalFile[] = [
  { id: 'd1', name: 'Resumo_Materia_T1.pdf',    size: '834 KB'  },
  { id: 'd2', name: 'Exercicios_Praticos.docx', size: '1.1 MB'  },
  { id: 'd3', name: 'Apontamentos_Aula.pdf',    size: '2.3 MB'  },
  { id: 'd4', name: 'Formulario_Final.pdf',     size: '512 KB'  },
  { id: 'd5', name: 'Projeto_Grupo.pptx',       size: '3.7 MB'  },
  { id: 'd6', name: 'Notas_Pessoais.txt',       size: '128 KB'  },
]
interface UCFileGroup { ucName: string; files: PersonalFile[] }
const MOCK_UC_FILES: UCFileGroup[] = [
  { ucName: 'Redes de Computadores', files: [{ id: 'uc1f1', name: 'Slides_Protocolos.pdf', size: '4.2 MB' }, { id: 'uc1f2', name: 'Ficha_TCP_IP.pdf', size: '890 KB' }] },
  { ucName: 'Programação Orientada a Objetos', files: [{ id: 'uc2f1', name: 'Enunciado_Projeto.pdf', size: '1.4 MB' }, { id: 'uc2f2', name: 'Guia_Laboratorio_3.pdf', size: '670 KB' }] },
  { ucName: 'Sistemas Operativos', files: [{ id: 'uc3f1', name: 'Resumo_Processos.pdf', size: '920 KB' }, { id: 'uc3f2', name: 'Exercicios_SO.pdf', size: '1.2 MB' }] },
]
const AI_RESPONSES = [
  'Ótima questão! Com base nos materiais deste espaço, vou explicar passo a passo o que precisas de saber.',
  'Analisei os recursos que tens aqui. A minha sugestão é focar primeiro nos conceitos fundamentais antes dos exercícios.',
  'Excelente! Este é um ponto crucial. Deixa-me estruturar uma resposta clara e objetiva para te ajudar.',
  'Perfeito! Vamos explorar este tema em detalhe com exemplos práticos aplicados à matéria.',
  'Com base no que partilhaste, posso identificar três pontos essenciais a considerar para a tua preparação.',
  'Boa questão! Este tema está diretamente relacionado com os materiais que tens guardados aqui.',
]
const MOCK_REMINDERS = [
  { id: 'r1', text: 'Rever fichas práticas antes do exame' },
  { id: 'r2', text: 'Completar o resumo do módulo 3' },
]

// ─── Helpers ───────────────────────────────────────────────────────────────────
function nowTime(): string { const d = new Date(); return `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}` }
function randomAI(): string { return AI_RESPONSES[Math.floor(Math.random() * AI_RESPONSES.length)] }
function fmtDate(iso: string): string { return new Date(iso).toLocaleDateString('pt-PT', { day: '2-digit', month: 'short', year: 'numeric' }) }

// ─── Route ─────────────────────────────────────────────────────────────────────
const route  = useRoute()
const router = useRouter()

const spaceId = computed(() => route.params.id as string)
const space   = computed(() => SPACE_MAP[spaceId.value] ?? FALLBACK_SPACE)

// ─── View state ────────────────────────────────────────────────────────────────
const isChatActive = ref(false)
const activeTab    = ref<TabId>('conversas')

// ─── Relational data ───────────────────────────────────────────────────────────
const folders = ref<FolderItem[]>([
  { id: 'f1', name: 'Fichas Práticas' },
  { id: 'f2', name: 'Projetos' },
])
const conversations = ref<ConversationSummary[]>([
  { id: 'c1', title: 'Resumo Módulo 1',        createdAt: '2026-04-24T10:00:00Z', folderId: null },
  { id: 'c2', title: 'Resolução Ficha 3',      createdAt: '2026-04-22T14:30:00Z', folderId: 'f1' },
  { id: 'c3', title: 'Análise do Exame 2024',  createdAt: '2026-04-18T09:15:00Z', folderId: null },
  { id: 'c4', title: 'Protocolo TCP — Dúvida', createdAt: '2026-04-15T16:00:00Z', folderId: 'f2' },
])

// ─── Navigation state ──────────────────────────────────────────────────────────
const activeFolderId       = ref<string | null>(null)
const activeConversationId = ref<string | null>(null)

// ─── Section toggles ───────────────────────────────────────────────────────────
const showFolders = ref(true)
const showConvs   = ref(true)

// ─── Materials state ───────────────────────────────────────────────────────────
const personalFiles = ref<PersonalFile[]>([
  { id: 'pf1', name: 'Resumo_Final.pdf', size: '1.2 MB' },
])

// ─── Modal state ───────────────────────────────────────────────────────────────
const isFolderModalOpen = ref(false)
const isFileModalOpen   = ref(false)

// ─── Chat state ────────────────────────────────────────────────────────────────
const messages    = ref<ChatMessage[]>([])
const inputValue  = ref('')
const isTyping    = ref(false)
const chatEndRef  = ref<HTMLDivElement | null>(null)

// ─── CreateFolder modal state ──────────────────────────────────────────────────
const folderModalName    = ref('')
const folderModalSelIds  = ref<string[]>([])

// ─── ImportMaterial modal state ────────────────────────────────────────────────
const pickerTab   = ref<PickerTab>('meus')
const pickerSel   = ref<PersonalFile[]>([])
const uploadStep  = ref<UploadStep>('dropzone')

// ─── Watch conversation changes ────────────────────────────────────────────────
watch(activeConversationId, (id) => {
  if (!id) { messages.value = []; inputValue.value = ''; isTyping.value = false; return }
  const conv = conversations.value.find(c => c.id === id)
  messages.value = [{
    id: `init-${id}`, type: 'incoming',
    text: conv ? `Olá! 👋 Estás na conversa "${conv.title}". Como posso ajudar-te com os materiais deste espaço?` : 'Olá! 👋 Nova conversa iniciada. Como posso ajudar?',
    time: nowTime(),
  }]
  inputValue.value = ''; isTyping.value = false
})

watch([messages, isTyping], async () => {
  await nextTick()
  chatEndRef.value?.scrollIntoView({ behavior: 'smooth' })
})

// ─── Action handlers ───────────────────────────────────────────────────────────
function handleCreateFolderConfirm() {
  if (!folderModalName.value.trim()) return
  const newFolderId = `f-${Date.now()}`
  folders.value = [...folders.value, { id: newFolderId, name: folderModalName.value.trim() }]
  if (folderModalSelIds.value.length > 0) {
    conversations.value = conversations.value.map(c =>
      folderModalSelIds.value.includes(c.id) ? { ...c, folderId: newFolderId } : c
    )
  }
  isFolderModalOpen.value  = false
  folderModalName.value    = ''
  folderModalSelIds.value  = []
}

function toggleFolderConv(id: string) {
  folderModalSelIds.value = folderModalSelIds.value.includes(id)
    ? folderModalSelIds.value.filter(x => x !== id)
    : [...folderModalSelIds.value, id]
}

function handleOpenConversation(convId: string) {
  activeConversationId.value = convId
  isChatActive.value = true
}

function handleNewConversation(folderId: string | null) {
  const newConv: ConversationSummary = { id: `c-${Date.now()}`, title: 'Nova Conversa', createdAt: new Date().toISOString(), folderId }
  conversations.value = [...conversations.value, newConv]
  activeConversationId.value = newConv.id
  isChatActive.value = true
}

function sendMessage(text: string) {
  const trimmed = text.trim()
  if (!trimmed) return
  messages.value = [...messages.value, { id: `msg-${Date.now()}`, type: 'outgoing', text: trimmed, time: nowTime() }]
  inputValue.value = ''
  isTyping.value = true
  setTimeout(() => {
    isTyping.value = false
    messages.value = [...messages.value, { id: `msg-ai-${Date.now()}`, type: 'incoming', text: randomAI(), time: nowTime() }]
  }, 1500)
}

function handleKeyDown(e: KeyboardEvent) { if (e.key === 'Enter') sendMessage(inputValue.value) }

function isPickerFileSelected(id: string) { return pickerSel.value.some(f => f.id === id) }
function togglePickerFile(file: PersonalFile) {
  pickerSel.value = pickerSel.value.some(f => f.id === file.id)
    ? pickerSel.value.filter(f => f.id !== file.id)
    : [...pickerSel.value, file]
}
function handlePickerDestination() {
  const mock: PersonalFile = { id: `upload-${Date.now()}`, name: `Ficheiro_Carregado_${pickerSel.value.length + 1}.pdf`, size: '2.4 MB' }
  pickerSel.value = [...pickerSel.value, mock]
  uploadStep.value = 'dropzone'
}
function handleImportConfirm() {
  personalFiles.value = [...personalFiles.value, ...pickerSel.value]
  isFileModalOpen.value = false
  pickerSel.value = []; pickerTab.value = 'meus'; uploadStep.value = 'dropzone'
}

// ─── Derived ───────────────────────────────────────────────────────────────────
const sortedFolders = computed(() => [...folders.value].sort((a, b) => a.name.localeCompare(b.name, 'pt')))
const looseConvs    = computed(() => conversations.value.filter(c => c.folderId === null).sort((a,b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()))
const activeFolder  = computed(() => folders.value.find(f => f.id === activeFolderId.value) ?? null)
const folderConvs   = computed(() => conversations.value.filter(c => c.folderId === activeFolderId.value).sort((a,b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()))
const activeConvTitle = computed(() => conversations.value.find(c => c.id === activeConversationId.value)?.title ?? 'Nova Conversa')
</script>

<template>
  <!-- ══ VIEW 2 — CHAT UI ══ -->
  <div v-if="isChatActive" style="height: 100%; display: flex; padding: 24px; gap: 24px; overflow: hidden; box-sizing: border-box;">

    <!-- Left: Context sidebar -->
    <div style="width: 300px; flex-shrink: 0; display: flex; flex-direction: column; gap: 16px; overflow-y: auto; padding-bottom: 16px;">
      <button
        style="display: inline-flex; align-items: center; gap: 8px; font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 10px; padding: 9px 14px; cursor: pointer; align-self: flex-start;"
        @click="() => { isChatActive = false; activeConversationId = null }"
      >
        <ArrowLeft :size="14" :stroke-width="2" color="#656966" />
        Voltar ao Espaço
      </button>

      <!-- Active conversation label -->
      <div style="background: #F8F8F8; border-radius: 10px; border: 1px solid #E5E5E5; padding: 12px 14px;">
        <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 11px; color: #9E9E9E; margin: 0 0 4px; text-transform: uppercase; letter-spacing: 0.06em;">Conversa activa</p>
        <p style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ activeConvTitle }}</p>
      </div>

      <!-- Space identity chip -->
      <div :style="{ background: space.gradient, borderRadius: '12px', padding: '16px 18px', position: 'relative', overflow: 'hidden' }">
        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.28);" />
        <div style="position: relative; z-index: 1;">
          <p style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #ffffff; margin: 0 0 4px;">{{ space.name }}</p>
          <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 12px; color: rgba(255,255,255,0.8); margin: 0;">{{ space.desc }}</p>
        </div>
      </div>

      <!-- Reminders -->
      <WidgetCard title="Lembretes">
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <div v-for="r in MOCK_REMINDERS" :key="r.id" style="display: flex; align-items: flex-start; gap: 10px;">
            <div style="width: 6px; height: 6px; border-radius: 50%; background: #009957; flex-shrink: 0; margin-top: 5px;" />
            <span style="font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #1E1E1E; line-height: 1.5;">{{ r.text }}</span>
          </div>
        </div>
      </WidgetCard>
    </div>

    <!-- Right: Chat area -->
    <div style="flex: 1; display: flex; flex-direction: column; height: 100%; overflow: hidden; min-width: 0;">
      <div style="flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; padding-bottom: 16px;">
        <div v-for="msg in messages" :key="msg.id" style="display: flex; flex-direction: column;" :style="{ alignItems: msg.type === 'outgoing' ? 'flex-end' : 'flex-start' }">
          <div
            :style="{
              maxWidth: '78%',
              background: msg.type === 'outgoing' ? '#1E1E1E' : '#F5F5F5',
              borderRadius: msg.type === 'outgoing' ? '16px 16px 4px 16px' : '16px 16px 16px 4px',
              padding: '12px 16px',
            }"
          >
            <p :style="{ fontFamily: 'Inter, sans-serif', fontWeight: 400, fontSize: '13px', color: msg.type === 'outgoing' ? '#ffffff' : '#1E1E1E', margin: 0, lineHeight: 1.65, whiteSpace: 'pre-line' }">{{ msg.text }}</p>
          </div>
          <span :style="{ fontFamily: 'Inter, sans-serif', fontWeight: 400, fontSize: '10px', color: '#C0C0C0', marginTop: '4px', paddingLeft: msg.type === 'incoming' ? '4px' : '0', paddingRight: msg.type === 'outgoing' ? '4px' : '0' }">{{ msg.time }}</span>
        </div>

        <div v-if="isTyping" style="display: flex; align-items: flex-start;">
          <div style="background: #F5F5F5; border-radius: 16px 16px 16px 4px; padding: 12px 18px; display: flex; align-items: center; gap: 5px;">
            <div v-for="i in [0,1,2]" :key="i" class="typing-dot" :style="{ animationDelay: `${i * 0.2}s` }" />
          </div>
        </div>
        <div ref="chatEndRef" />
      </div>

      <!-- Pinned input -->
      <div style="flex-shrink: 0; display: flex; align-items: center; height: 48px; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 9999px; padding-left: 16px; padding-right: 4px; gap: 8px;">
        <input
          v-model="inputValue"
          type="text"
          placeholder="Escreve a tua mensagem..."
          style="flex: 1; font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #1E1E1E; background: none; border: none; outline: none;"
          @keydown="handleKeyDown"
        />
        <button style="width: 36px; height: 36px; border-radius: 50%; background: #009957; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin: 4px;" @click="sendMessage(inputValue)">
          <Send :size="15" :stroke-width="2" color="#ffffff" />
        </button>
      </div>
    </div>
  </div>

  <!-- ══ VIEW 1 — SPACE HUB ══ -->
  <template v-else>
    <div style="height: 100%; overflow-y: auto; box-sizing: border-box; padding-bottom: 96px;">
      <div style="max-width: 1200px; margin: 0 auto; padding: 32px;">

        <!-- Header banner -->
        <div :style="{ height: '160px', borderRadius: '20px', position: 'relative', overflow: 'hidden', display: 'flex', flexDirection: 'column', justifyContent: 'space-between', padding: '24px', background: space.gradient }">
          <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.30);" />
          <div style="position: relative; z-index: 1;">
            <button
              class="transition-opacity hover:opacity-80"
              style="display: inline-flex; align-items: center; gap: 7px; font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #ffffff; background: rgba(255,255,255,0.18); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.28); border-radius: 8px; padding: 7px 13px; cursor: pointer;"
              @click="router.push({ name: 'ucs', query: { tab: 'espacos' } })"
            >
              <ArrowLeft :size="13" :stroke-width="2.2" color="#ffffff" />
              Voltar
            </button>
          </div>
          <div style="position: relative; z-index: 1;">
            <p style="font-family: Inter, sans-serif; font-weight: 700; font-size: 28px; color: #ffffff; margin: 0 0 6px; line-height: 1.15;">{{ space.name }}</p>
            <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: rgba(255,255,255,0.82); margin: 0;">{{ space.desc }}</p>
          </div>
        </div>

        <!-- Tab bar -->
        <div style="display: flex; border-bottom: 1px solid #E5E5E5; margin-top: 32px; margin-bottom: 28px;">
          <button
            v-for="tab in [{ id: 'conversas', label: 'Conversas' }, { id: 'materiais', label: 'Os meus materiais' }]"
            :key="tab.id"
            :style="{
              fontFamily: 'Inter, sans-serif',
              fontWeight: activeTab === tab.id ? 600 : 400,
              fontSize: '14px',
              color: activeTab === tab.id ? '#009957' : '#656966',
              background: 'none', border: 'none',
              borderBottom: activeTab === tab.id ? '2px solid #009957' : '2px solid transparent',
              padding: '10px 20px', cursor: 'pointer', marginBottom: '-1px',
              transition: 'color 0.15s ease',
            }"
            @click="activeTab = (tab.id as TabId)"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- TAB 1: Conversas -->
        <div v-if="activeTab === 'conversas'" style="display: flex; flex-direction: column; gap: 32px;">

          <!-- Root view -->
          <template v-if="activeFolderId === null">
            <!-- PASTAS -->
            <div>
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; user-select: none;">
                <div style="display: flex; align-items: center; gap: 6px; cursor: pointer;" @click="showFolders = !showFolders">
                  <component :is="showFolders ? ChevronUp : ChevronDown" :size="15" :stroke-width="2" color="#656966" />
                  <span style="font-family: Inter, sans-serif; font-weight: 600; font-size: 15px; color: #1E1E1E;">Pastas</span>
                </div>
                <button style="display: inline-flex; align-items: center; gap: 6px; font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #009957; background: rgba(0,153,87,0.07); border: none; border-radius: 8px; padding: 6px 12px; cursor: pointer;" @click.stop="isFolderModalOpen = true">
                  <Plus :size="12" :stroke-width="2.5" color="#009957" />
                  Nova Pasta
                </button>
              </div>
              <template v-if="showFolders">
                <p v-if="sortedFolders.length === 0" style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #9E9E9E; margin: 0;">Nenhuma pasta criada.</p>
                <div v-else style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px;">
                  <div
                    v-for="folder in sortedFolders"
                    :key="folder.id"
                    class="transition-shadow hover:shadow-sm"
                    style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; cursor: pointer;"
                    @click="activeFolderId = folder.id"
                  >
                    <Folder :size="20" :stroke-width="1.8" color="#656966" />
                    <div style="flex: 1; min-width: 0;">
                      <p style="font-family: Inter, sans-serif; font-weight: 600; font-size: 13px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ folder.name }}</p>
                      <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 11px; color: #9E9E9E; margin: 0;">{{ conversations.filter(c => c.folderId === folder.id).length }} conversas</p>
                    </div>
                  </div>
                </div>
              </template>
            </div>

            <!-- CONVERSAS SOLTAS -->
            <div>
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; user-select: none;">
                <div style="display: flex; align-items: center; gap: 6px; cursor: pointer;" @click="showConvs = !showConvs">
                  <component :is="showConvs ? ChevronUp : ChevronDown" :size="15" :stroke-width="2" color="#656966" />
                  <span style="font-family: Inter, sans-serif; font-weight: 600; font-size: 15px; color: #1E1E1E;">Conversas Soltas</span>
                </div>
                <button style="display: inline-flex; align-items: center; gap: 6px; font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #009957; background: rgba(0,153,87,0.07); border: none; border-radius: 8px; padding: 6px 12px; cursor: pointer;" @click.stop="handleNewConversation(null)">
                  <Plus :size="12" :stroke-width="2.5" color="#009957" />
                  Nova Conversa
                </button>
              </div>
              <template v-if="showConvs">
                <p v-if="looseConvs.length === 0" style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #9E9E9E; margin: 0;">Nenhuma conversa solta.</p>
                <div v-else style="display: flex; flex-direction: column; gap: 10px;">
                  <div
                    v-for="conv in looseConvs"
                    :key="conv.id"
                    class="transition-shadow hover:shadow-sm"
                    style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; gap: 14px; cursor: pointer;"
                    @click="handleOpenConversation(conv.id)"
                  >
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(0,153,87,0.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                      <MessageCircle :size="18" :stroke-width="1.8" color="#009957" />
                    </div>
                    <div style="flex: 1; min-width: 0;">
                      <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ conv.title }}</p>
                      <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 12px; color: #9E9E9E; margin: 0;">{{ fmtDate(conv.createdAt) }}</p>
                    </div>
                    <ChevronRight :size="15" :stroke-width="1.8" color="#BDBABA" />
                  </div>
                </div>
              </template>
            </div>
          </template>

          <!-- Inside a folder -->
          <template v-else>
            <div>
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                  <button style="display: inline-flex; align-items: center; gap: 7px; font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 8px; padding: 7px 12px; cursor: pointer;" @click="activeFolderId = null">
                    <ArrowLeft :size="13" :stroke-width="2" color="#656966" />
                    Voltar às Pastas
                  </button>
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <Folder :size="18" :stroke-width="1.8" color="#1E1E1E" />
                    <span style="font-family: Inter, sans-serif; font-weight: 600; font-size: 16px; color: #1E1E1E;">{{ activeFolder?.name ?? '' }}</span>
                  </div>
                </div>
                <button style="display: inline-flex; align-items: center; gap: 7px; font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #ffffff; background: #009957; border: none; border-radius: 10px; padding: 9px 16px; cursor: pointer;" @click="handleNewConversation(activeFolderId)">
                  <Plus :size="14" :stroke-width="2.2" color="#ffffff" />
                  Nova Conversa
                </button>
              </div>
              <div v-if="folderConvs.length === 0" style="padding: 40px 0; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                <Folder :size="32" :stroke-width="1.2" color="#C5C5C5" />
                <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #9E9E9E; margin: 0;">Esta pasta está vazia.</p>
              </div>
              <div v-else style="display: flex; flex-direction: column; gap: 10px;">
                <div
                  v-for="conv in folderConvs"
                  :key="conv.id"
                  class="transition-shadow hover:shadow-sm"
                  style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; gap: 14px; cursor: pointer;"
                  @click="handleOpenConversation(conv.id)"
                >
                  <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(0,153,87,0.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <MessageCircle :size="18" :stroke-width="1.8" color="#009957" />
                  </div>
                  <div style="flex: 1; min-width: 0;">
                    <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ conv.title }}</p>
                    <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 12px; color: #9E9E9E; margin: 0;">{{ fmtDate(conv.createdAt) }}</p>
                  </div>
                  <ChevronRight :size="15" :stroke-width="1.8" color="#BDBABA" />
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- TAB 2: Os meus materiais -->
        <div v-else-if="activeTab === 'materiais'">
          <div
            class="hover:border-[#009957] transition-colors cursor-pointer"
            style="height: 120px; border: 2px dashed #E5E5E5; border-radius: 12px; background: #FAFAFA; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; margin-bottom: 24px;"
            @click="isFileModalOpen = true"
          >
            <UploadCloud :size="28" :stroke-width="1.5" color="#BDBABA" />
            <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #9E9E9E; margin: 0;">Clica para importar material</p>
          </div>
          <div v-if="personalFiles.length > 0" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px;">
            <div
              v-for="file in personalFiles"
              :key="file.id"
              class="transition-shadow hover:shadow-sm"
              style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 10px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;"
            >
              <div style="width: 36px; height: 36px; border-radius: 8px; background: #FFF3F3; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <FileText :size="18" :stroke-width="1.8" color="#E53935" />
              </div>
              <div style="flex: 1; min-width: 0;">
                <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ file.name }}</p>
                <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 11px; color: #9E9E9E; margin: 0;">{{ file.size }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Criar Nova Pasta -->
    <Teleport to="body">
      <div v-if="isFolderModalOpen" style="position: fixed; inset: 0; z-index: 1000; background: rgba(0,0,0,0.42); display: flex; align-items: center; justify-content: center;" @click="() => { isFolderModalOpen = false; folderModalName = ''; folderModalSelIds = [] }">
        <div style="width: 500px; background: #ffffff; border-radius: 20px; padding: 24px; display: flex; flex-direction: column; gap: 20px; box-shadow: 0 16px 60px rgba(0,0,0,0.18);" @click.stop>
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <p style="font-family: Inter, sans-serif; font-weight: 700; font-size: 18px; color: #1E1E1E; margin: 0;">Criar Nova Pasta</p>
            <button style="width: 30px; height: 30px; border: 1px solid #E5E5E5; border-radius: 8px; background: none; cursor: pointer; display: flex; align-items: center; justify-content: center;" @click="() => { isFolderModalOpen = false; folderModalName = ''; folderModalSelIds = [] }">
              <X :size="14" :stroke-width="2" color="#9E9E9E" />
            </button>
          </div>
          <div style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966;">Nome da pasta</label>
            <input v-model="folderModalName" autofocus type="text" placeholder="ex: Fichas de Exame" style="font-family: Inter, sans-serif; font-size: 14px; color: #1E1E1E; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 10px; padding: 10px 12px; outline: none; width: 100%; box-sizing: border-box;" @keydown.enter="handleCreateFolderConfirm" />
          </div>
          <div style="display: flex; flex-direction: column; gap: 10px;">
            <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; margin: 0;">
              Mover conversas soltas para esta pasta <span style="font-weight: 400; color: #B0B0B0;">(opcional)</span>
            </p>
            <p v-if="looseConvs.length === 0" style="font-family: Inter, sans-serif; font-size: 13px; color: #B0B0B0; margin: 0;">Não há conversas soltas para mover.</p>
            <div v-else style="max-height: 200px; overflow-y: auto; display: flex; flex-direction: column; gap: 6px;">
              <div
                v-for="conv in looseConvs"
                :key="conv.id"
                :style="{ display: 'flex', alignItems: 'center', gap: '12px', padding: '10px 12px', borderRadius: '10px', border: `1px solid ${folderModalSelIds.includes(conv.id) ? '#009957' : '#E5E5E5'}`, background: folderModalSelIds.includes(conv.id) ? 'rgba(0,153,87,0.04)' : '#FAFAFA', cursor: 'pointer' }"
                @click="toggleFolderConv(conv.id)"
              >
                <div :style="{ width: '18px', height: '18px', borderRadius: '5px', border: `2px solid ${folderModalSelIds.includes(conv.id) ? '#009957' : '#BDBABA'}`, background: folderModalSelIds.includes(conv.id) ? '#009957' : '#ffffff', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }">
                  <Check v-if="folderModalSelIds.includes(conv.id)" :size="11" :stroke-width="3" color="#ffffff" />
                </div>
                <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(0,153,87,0.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <MessageCircle :size="15" :stroke-width="1.8" color="#009957" />
                </div>
                <div style="flex: 1; min-width: 0;">
                  <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ conv.title }}</p>
                  <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 11px; color: #9E9E9E; margin: 0;">{{ fmtDate(conv.createdAt) }}</p>
                </div>
              </div>
            </div>
          </div>
          <div style="display: flex; gap: 10px; margin-top: 4px;">
            <button style="flex: 1; font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 10px; padding: 10px 0; cursor: pointer;" @click="() => { isFolderModalOpen = false; folderModalName = ''; folderModalSelIds = [] }">Cancelar</button>
            <button :style="{ flex: 1, fontFamily: 'Inter, sans-serif', fontWeight: 600, fontSize: '14px', color: '#ffffff', background: folderModalName.trim() ? '#009957' : '#C5C5C5', border: 'none', borderRadius: '10px', padding: '10px 0', cursor: folderModalName.trim() ? 'pointer' : 'not-allowed' }" @click="handleCreateFolderConfirm">Criar Pasta</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal: Importar Material -->
    <Teleport to="body">
      <div v-if="isFileModalOpen" style="position: fixed; inset: 0; z-index: 1000; background: rgba(0,0,0,0.42); display: flex; align-items: center; justify-content: center;" @click="() => { isFileModalOpen = false; pickerSel = []; pickerTab = 'meus'; uploadStep = 'dropzone' }">
        <div style="width: 800px; height: 600px; background: #ffffff; border-radius: 20px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 16px 60px rgba(0,0,0,0.18);" @click.stop>
          <!-- Modal header -->
          <div style="display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; flex-shrink: 0;">
            <p style="font-family: Inter, sans-serif; font-weight: 700; font-size: 18px; color: #1E1E1E; margin: 0;">Importar Material</p>
            <button style="width: 30px; height: 30px; border: 1px solid #E5E5E5; border-radius: 8px; background: none; cursor: pointer; display: flex; align-items: center; justify-content: center;" @click="() => { isFileModalOpen = false; pickerSel = []; pickerTab = 'meus'; uploadStep = 'dropzone' }">
              <X :size="14" :stroke-width="2" color="#9E9E9E" />
            </button>
          </div>
          <!-- Picker tabs -->
          <div style="display: flex; border-bottom: 1px solid #E5E5E5; padding: 0 24px; margin-top: 16px; flex-shrink: 0;">
            <button
              v-for="tab in [{ id: 'meus', label: 'Os meus materiais' }, { id: 'ucs', label: 'Materiais das UCs' }, { id: 'upload', label: 'Carregar' }]"
              :key="tab.id"
              :style="{ fontFamily: 'Inter, sans-serif', fontWeight: pickerTab === tab.id ? 600 : 400, fontSize: '13px', color: pickerTab === tab.id ? '#009957' : '#656966', background: 'none', border: 'none', borderBottom: pickerTab === tab.id ? '2px solid #009957' : '2px solid transparent', padding: '8px 16px', cursor: 'pointer', marginBottom: '-1px', whiteSpace: 'nowrap' }"
              @click="() => { pickerTab = (tab.id as PickerTab); uploadStep = 'dropzone' }"
            >
              {{ tab.label }}
            </button>
          </div>
          <!-- Content area -->
          <div style="flex: 1; overflow-y: auto; padding: 20px 24px;">

            <!-- Os meus materiais -->
            <div v-if="pickerTab === 'meus'" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px;">
              <div
                v-for="file in MOCK_DRIVE_FILES"
                :key="file.id"
                :style="{ border: `2px solid ${isPickerFileSelected(file.id) ? '#009957' : '#E5E5E5'}`, borderRadius: '12px', padding: '14px', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '10px', cursor: 'pointer', background: isPickerFileSelected(file.id) ? 'rgba(0,153,87,0.04)' : '#ffffff', position: 'relative' }"
                @click="togglePickerFile(file)"
              >
                <div v-if="isPickerFileSelected(file.id)" style="position: absolute; top: 8px; right: 8px; width: 20px; height: 20px; border-radius: 50%; background: #009957; display: flex; align-items: center; justify-content: center;">
                  <Check :size="11" :stroke-width="3" color="#ffffff" />
                </div>
                <div style="width: 48px; height: 48px; border-radius: 10px; background: #FFF3F3; display: flex; align-items: center; justify-content: center;">
                  <FileText :size="24" :stroke-width="1.5" color="#E53935" />
                </div>
                <div style="text-align: center; width: 100%;">
                  <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 11px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ file.name }}</p>
                  <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 10px; color: #9E9E9E; margin: 0;">{{ file.size }}</p>
                </div>
              </div>
            </div>

            <!-- Materiais das UCs -->
            <div v-else-if="pickerTab === 'ucs'" style="display: flex; flex-direction: column; gap: 24px;">
              <div v-for="group in MOCK_UC_FILES" :key="group.ucName">
                <p style="font-family: Inter, sans-serif; font-weight: 600; font-size: 13px; color: #1E1E1E; margin: 0 0 12px;">{{ group.ucName }}</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px;">
                  <div
                    v-for="file in group.files"
                    :key="file.id"
                    :style="{ border: `2px solid ${isPickerFileSelected(file.id) ? '#009957' : '#E5E5E5'}`, borderRadius: '12px', padding: '14px', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '10px', cursor: 'pointer', background: isPickerFileSelected(file.id) ? 'rgba(0,153,87,0.04)' : '#ffffff', position: 'relative' }"
                    @click="togglePickerFile(file)"
                  >
                    <div v-if="isPickerFileSelected(file.id)" style="position: absolute; top: 8px; right: 8px; width: 20px; height: 20px; border-radius: 50%; background: #009957; display: flex; align-items: center; justify-content: center;">
                      <Check :size="11" :stroke-width="3" color="#ffffff" />
                    </div>
                    <div style="width: 48px; height: 48px; border-radius: 10px; background: #FFF3F3; display: flex; align-items: center; justify-content: center;">
                      <FileText :size="24" :stroke-width="1.5" color="#E53935" />
                    </div>
                    <div style="text-align: center; width: 100%;">
                      <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 11px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ file.name }}</p>
                      <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 10px; color: #9E9E9E; margin: 0;">{{ file.size }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Carregar -->
            <div v-else-if="pickerTab === 'upload'">
              <!-- Dropzone -->
              <div v-if="uploadStep === 'dropzone'" style="display: flex; flex-direction: column; align-items: center; gap: 20px;">
                <div style="width: 100%; height: 200px; border: 2px dashed #E5E5E5; border-radius: 16px; background: #FAFAFA; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;">
                  <UploadCloud :size="40" :stroke-width="1.2" color="#BDBABA" />
                  <div style="text-align: center;">
                    <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 15px; color: #1E1E1E; margin: 0;">Arrasta ficheiros para aqui</p>
                    <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #9E9E9E; margin: 0;">PDF, DOCX, PPTX — até 50 MB por ficheiro</p>
                  </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px; width: 100%;">
                  <div style="flex: 1; height: 1px; background: #E5E5E5;" />
                  <span style="font-family: Inter, sans-serif; font-size: 12px; color: #B0B0B0;">ou</span>
                  <div style="flex: 1; height: 1px; background: #E5E5E5;" />
                </div>
                <button style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #ffffff; background: #009957; border: none; border-radius: 10px; padding: 11px 28px; cursor: pointer;" @click="uploadStep = 'destination'">Procurar no computador</button>
              </div>
              <!-- Destination choice -->
              <div v-else-if="uploadStep === 'destination'" style="display: flex; flex-direction: column; align-items: center; gap: 20px;">
                <div style="text-align: center; margin-bottom: 8px;">
                  <p style="font-family: Inter, sans-serif; font-weight: 700; font-size: 17px; color: #1E1E1E; margin: 0 0 6px;">Onde pretendes guardar este ficheiro?</p>
                  <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #9E9E9E; margin: 0;">Escolhe onde o ficheiro ficará acessível após o upload.</p>
                </div>
                <button class="transition-all hover:shadow-md" style="width: 100%; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 14px; padding: 18px 20px; cursor: pointer; text-align: left; display: flex; align-items: center; gap: 16px;" @click="handlePickerDestination">
                  <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(0,153,87,0.09); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <Folder :size="22" :stroke-width="1.8" color="#009957" />
                  </div>
                  <div>
                    <p style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #1E1E1E; margin: 0;">Apenas neste Espaço</p>
                    <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 12px; color: #9E9E9E; margin: 0;">O ficheiro fica disponível apenas dentro deste espaço de estudo.</p>
                  </div>
                </button>
                <button class="transition-all hover:shadow-md" style="width: 100%; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 14px; padding: 18px 20px; cursor: pointer; text-align: left; display: flex; align-items: center; gap: 16px;" @click="handlePickerDestination">
                  <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(102,126,234,0.09); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <UploadCloud :size="22" :stroke-width="1.8" color="#667eea" />
                  </div>
                  <div>
                    <p style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #1E1E1E; margin: 0;">Neste Espaço e na minha Conta Geral</p>
                    <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 12px; color: #9E9E9E; margin: 0;">O ficheiro também fica acessível nos teus materiais pessoais globais.</p>
                  </div>
                </button>
                <button style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #9E9E9E; background: none; border: none; cursor: pointer;" @click="uploadStep = 'dropzone'">← Cancelar</button>
              </div>
            </div>
          </div>

          <!-- Modal footer -->
          <div style="flex-shrink: 0; border-top: 1px solid #E5E5E5; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
            <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #9E9E9E; margin: 0;">
              {{ pickerSel.length === 0 ? 'Nenhum ficheiro selecionado' : `${pickerSel.length} ficheiro${pickerSel.length > 1 ? 's' : ''} selecionado${pickerSel.length > 1 ? 's' : ''}` }}
            </p>
            <div style="display: flex; gap: 10px;">
              <button style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 10px; padding: 9px 18px; cursor: pointer;" @click="() => { isFileModalOpen = false; pickerSel = []; pickerTab = 'meus'; uploadStep = 'dropzone' }">Cancelar</button>
              <button :style="{ fontFamily: 'Inter, sans-serif', fontWeight: 600, fontSize: '13px', color: '#ffffff', background: pickerSel.length > 0 ? '#009957' : '#C5C5C5', border: 'none', borderRadius: '10px', padding: '9px 18px', cursor: pickerSel.length > 0 ? 'pointer' : 'not-allowed' }" @click="handleImportConfirm">Adicionar ao Espaço</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </template>
</template>

<style scoped>
.typing-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: #BDBABA;
  animation: bounce 1.2s infinite;
}
@keyframes bounce {
  0%, 60%, 100% { transform: translateY(0); }
  30% { transform: translateY(-6px); }
}
</style>
