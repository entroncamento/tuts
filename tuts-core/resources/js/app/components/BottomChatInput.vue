<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Plus, Send, FileText, Zap, BookOpen, X } from '@lucide/vue'

defineOptions({ name: 'BottomChatInput' })
import { useShellStore } from '@/app/stores/shell'
import ImportMaterialModal from './ImportMaterialModal.vue'
import type { PersonalFile } from './ImportMaterialModal.vue'

// ─── URL segment extractor ────────────────────────────────────────────────────
// Mirrors the React extractSegmentAfter utility exactly.
function extractSegmentAfter(pathname: string, prefix: string): string | undefined {
  const parts = pathname.split('/').filter(Boolean)
  const idx   = parts.indexOf(prefix)
  if (idx === -1 || idx + 1 >= parts.length) return undefined
  return parts[idx + 1]
}

// ─── Mock autocomplete data ───────────────────────────────────────────────────
const COMMANDS = [
  { id: 'c1', cmd: '/quiz',    desc: 'Gerar um teste rápido' },
  { id: 'c2', cmd: '/resumo',  desc: 'Sintetizar matéria'    },
  { id: 'c3', cmd: '/grafico', desc: 'Criar mapa mental'     },
]

const MENTIONS = [
  { id: 'm1', name: 'Redes de Computadores' },
  { id: 'm2', name: 'Sistemas Operativos'   },
  { id: 'm3', name: 'Matemática Discreta'   },
  { id: 'm4', name: 'Exames 2026'           },
]

// ─── Dependencies ─────────────────────────────────────────────────────────────
const route      = useRoute()
const router     = useRouter()
const shellStore = useShellStore()

// ─── State ────────────────────────────────────────────────────────────────────
const text            = ref('')
const isFileModalOpen = ref(false)
const attachedFiles   = ref<PersonalFile[]>([])
const activePopup     = ref<'mention' | 'command' | null>(null)
const inputRef        = ref<HTMLInputElement | null>(null)

const hasContent = computed(() => text.value.trim().length > 0)

// ─── Global focus handler registration ───────────────────────────────────────
onMounted(() => {
  shellStore.registerFocusHandler(() => inputRef.value?.focus())
})

onUnmounted(() => {
  shellStore.unregisterFocusHandler()
})

// ─── @mention injection from ChatHubPage modals ───────────────────────────────
// ChatHubPage dispatches a "insertChatMention" CustomEvent on window, matching
// the React event-bus pattern exactly — no framework coupling needed.
function handleMentionInsert(e: Event): void {
  const customEvent = e as CustomEvent<string>
  text.value += customEvent.detail
  inputRef.value?.focus()
}

onMounted(() => {
  window.addEventListener('insertChatMention', handleMentionInsert)
})

onUnmounted(() => {
  window.removeEventListener('insertChatMention', handleMentionInsert)
})

// ─── Click-outside: close autocomplete popover ───────────────────────────────
function handleOutsideClick(e: MouseEvent): void {
  const bar = document.getElementById('tuts-chat-bar')
  if (bar && !bar.contains(e.target as Node)) {
    activePopup.value = null
  }
}

onMounted(() => {
  document.addEventListener('mousedown', handleOutsideClick, true)
})

onUnmounted(() => {
  document.removeEventListener('mousedown', handleOutsideClick, true)
})

// ─── Input parsing — triggers autocomplete popover ───────────────────────────
function handleTextInput(e: Event): void {
  const val = (e.target as HTMLInputElement).value
  text.value = val

  if      (val.endsWith('/'))  activePopup.value = 'command'
  else if (val.endsWith('@'))  activePopup.value = 'mention'
  else if (!val.includes('/') && !val.includes('@')) activePopup.value = null

  if (val.trim() === '') activePopup.value = null
}

// ─── Suggestion selection ─────────────────────────────────────────────────────
function handleSelectSuggestion(suggestion: string): void {
  text.value = text.value.slice(0, -1) + suggestion + ' '
  activePopup.value = null
  inputRef.value?.focus()
}

// ─── Keyboard handler ─────────────────────────────────────────────────────────
function handleKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape') {
    activePopup.value = null
    return
  }
  if (e.key === 'Enter' && !e.shiftKey) {
    if (activePopup.value) return // let user pick a suggestion first
    e.preventDefault()
    handleSend()
  }
}

// ─── Send logic ───────────────────────────────────────────────────────────────
// Mirrors the React handleSend routing logic exactly.
function handleSend(): void {
  const pathname = route.path
  const trimmed  = text.value.trim()

  if (trimmed === '' && attachedFiles.value.length === 0 && pathname !== '/calendar') return

  if (pathname === '/calendar') {
    router.push({ name: 'planning' })
    text.value = ''
    return
  }

  let sourceContext = 'global'
  let sourceId: string | null = null

  if (pathname.startsWith('/ucs/')) {
    sourceContext = 'uc'
    sourceId = extractSegmentAfter(pathname, 'ucs') ?? null
  } else if (pathname.startsWith('/espacos/')) {
    sourceContext = 'space'
    sourceId = extractSegmentAfter(pathname, 'espacos') ?? null
  } else if (trimmed.startsWith('@')) {
    sourceContext = 'mention'
  }

  router.push({
    name:  'chat',
    state: { initialMessage: trimmed, sourceContext, sourceId },
  })
  text.value = ''
  activePopup.value = null
}

// ─── File attachment helpers ──────────────────────────────────────────────────
function handleFilesConfirmed(files: PersonalFile[]): void {
  const existingIds = new Set(attachedFiles.value.map((f) => f.id))
  attachedFiles.value = [
    ...attachedFiles.value,
    ...files.filter((f) => !existingIds.has(f.id)),
  ]
  isFileModalOpen.value = false
}

function removeAttachedFile(id: string): void {
  attachedFiles.value = attachedFiles.value.filter((f) => f.id !== id)
}
</script>

<template>
  <div
    id="tuts-chat-bar"
    class="fixed bottom-0 left-0 right-0 flex flex-col items-center"
    style="z-index: 50; padding-bottom: 20px; padding-left: 80px; background: linear-gradient(to top, #ffffff 60%, transparent); transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);"
  >

    <!-- ── Autocomplete popover anchor ──────────────────────────────────── -->
    <div style="position: relative; width: 100%; max-width: 860px;">
      <div
        v-if="activePopup"
        style="position: absolute; bottom: 100%; left: 40px; margin-bottom: 12px; background: #ffffff; border-radius: 16px; box-shadow: 0 12px 40px rgba(0,0,0,0.12); border: 1px solid #E5E5E5; padding: 8px; display: flex; flex-direction: column; gap: 4px; z-index: 60; min-width: 240px; max-height: 200px; overflow-y: auto;"
      >
        <!-- Command suggestions -->
        <button
          v-if="activePopup === 'command'"
          v-for="c in COMMANDS"
          :key="c.id"
          class="hover:bg-[#F5F5F5] transition-colors"
          style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: none; border: none; border-radius: 8px; cursor: pointer; text-align: left; width: 100%;"
          @click="handleSelectSuggestion(c.cmd)"
        >
          <div style="background: #FFF7E6; padding: 6px; border-radius: 6px; flex-shrink: 0;">
            <Zap :size="14" color="#F57C00" />
          </div>
          <div>
            <p style="margin: 0; font-family: Inter, sans-serif; font-weight: 600; font-size: 13px; color: #1E1E1E;">{{ c.cmd }}</p>
            <p style="margin: 0; font-family: Inter, sans-serif; font-weight: 400; font-size: 11px; color: #9E9E9E;">{{ c.desc }}</p>
          </div>
        </button>

        <!-- Mention suggestions -->
        <button
          v-if="activePopup === 'mention'"
          v-for="m in MENTIONS"
          :key="m.id"
          class="hover:bg-[#F5F5F5] transition-colors"
          style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: none; border: none; border-radius: 8px; cursor: pointer; text-align: left; width: 100%;"
          @click="handleSelectSuggestion(`@${m.name}`)"
        >
          <div style="background: #E6F4EA; padding: 6px; border-radius: 6px; flex-shrink: 0;">
            <BookOpen :size="14" color="#009957" />
          </div>
          <p style="margin: 0; font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E;">{{ m.name }}</p>
        </button>
      </div>
    </div>

    <!-- ── Input surface ─────────────────────────────────────────────────── -->
    <div
      :style="{
        width: '100%', maxWidth: '860px',
        background: '#ffffff',
        borderRadius: attachedFiles.length > 0 ? '20px' : '40px',
        border: '1px solid #E5E5E5',
        boxShadow: '0 8px 32px rgba(0,0,0,0.08)',
        display: 'flex', flexDirection: 'column',
        padding: attachedFiles.length > 0 ? '12px 12px 10px 16px' : '10px 10px 10px 16px',
        gap: '0',
        transition: 'border-radius 0.2s',
      }"
    >

      <!-- Attached file chips -->
      <div
        v-if="attachedFiles.length > 0"
        style="display: flex; flex-wrap: wrap; gap: 6px; padding-bottom: 10px; border-bottom: 1px solid #F0F0F0; margin-bottom: 10px;"
      >
        <div
          v-for="f in attachedFiles"
          :key="f.id"
          style="display: flex; align-items: center; gap: 6px; background: #F5F5F5; padding: 4px 10px; border-radius: 8px; border: 1px solid #E5E5E5; max-width: 200px;"
        >
          <FileText :size="12" color="#009957" style="flex-shrink: 0;" />
          <span style="font-size: 11px; font-weight: 500; color: #1E1E1E; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0;">
            {{ f.name }}
          </span>
          <button
            :aria-label="`Remover ${f.name}`"
            style="background: none; border: none; cursor: pointer; padding: 2px; display: flex; flex-shrink: 0;"
            @click="removeAttachedFile(f.id)"
          >
            <X :size="12" color="#9E9E9E" />
          </button>
        </div>
      </div>

      <!-- Input row: + button · text input · send button -->
      <div style="display: flex; align-items: center; gap: 12px;">

        <!-- + opens ImportMaterialModal directly -->
        <button
          aria-label="Adicionar ficheiro"
          class="flex items-center justify-center rounded-full flex-shrink-0 hover:bg-[#EBEBEB] transition-colors"
          style="width: 36px; height: 36px; background: #F5F5F5; border: none; cursor: pointer; outline: none;"
          @click="isFileModalOpen = true"
        >
          <Plus :size="16" :stroke-width="2" color="#1E1E1E" />
        </button>

        <input
          ref="inputRef"
          type="text"
          :value="text"
          placeholder="Faz qualquer pergunta..."
          style="flex: 1; border: none; outline: none; background: transparent; font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #1E1E1E;"
          @input="handleTextInput"
          @keydown="handleKeydown"
        />

        <button
          aria-label="Enviar mensagem"
          class="flex items-center justify-center rounded-full flex-shrink-0 hover:opacity-[0.82] transition-opacity"
          :style="{
            width:      '40px',
            height:     '40px',
            background: hasContent || attachedFiles.length > 0 ? '#009957' : '#1E1E1E',
            border:     'none',
            cursor:     'pointer',
            outline:    'none',
            transition: 'background 0.2s',
          }"
          @click="handleSend"
        >
          <Send :size="16" :stroke-width="2" color="#ffffff" />
        </button>
      </div>
    </div>

    <!-- ── Footer note ───────────────────────────────────────────────────── -->
    <div style="margin-top: 8px; display: flex; align-items: center; gap: 6px;">
      <span style="font-family: Inter, sans-serif; font-weight: 400; font-size: 10px; color: #C0C0C0; text-align: center;">
        Criado por alunos e docentes que valorizam a qualidade, e com princípios de
        <strong style="font-weight: 700; text-decoration: underline;">Responsible AI</strong>
      </span>
    </div>

    <!-- ── ImportMaterialModal ───────────────────────────────────────────── -->
    <ImportMaterialModal
      v-if="isFileModalOpen"
      @confirm="handleFilesConfirmed"
      @close="isFileModalOpen = false"
    />

  </div>
</template>
