<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  Download,
  Edit3,
  Eye,
  FileText,
  Folder,
  FolderPlus,
  MessageCircle,
  MoreVertical,
  Plus,
  Trash2,
  UploadCloud,
} from '@lucide/vue'
import {
  createSpaceConversation,
  createSpaceFolder,
  deleteSpaceFolder,
  deleteSpaceMaterial,
  fetchSpace,
  fetchSpaceConversations,
  fetchSpaceFolders,
  fetchSpaceMaterials,
  moveSpaceConversationToFolder,
  moveSpaceMaterialToFolder,
  type SpaceChatSummary,
  type SpaceFolder,
  type SpaceMaterial,
  type StudySpace,
  updateSpaceFolder,
  uploadSpaceMaterial,
} from '@/app/services/spaces'

const route = useRoute()
const router = useRouter()

type SpaceTab = 'conversas' | 'materiais' | 'organizacao'
type FolderFilter = 'all' | 'none' | number

const spaceId = computed(() => String(route.params.id))
const space = ref<StudySpace | null>(null)
const chats = ref<SpaceChatSummary[]>([])
const materials = ref<SpaceMaterial[]>([])
const folders = ref<SpaceFolder[]>([])
const loading = ref(false)
const creatingChat = ref(false)
const uploadingMaterial = ref(false)
const deletingMaterialId = ref<number | null>(null)
const deletingFolderId = ref<number | null>(null)
const movingChatId = ref<number | null>(null)
const movingMaterialId = ref<number | null>(null)
const errorMessage = ref<string | null>(null)
const materialError = ref<string | null>(null)
const folderError = ref<string | null>(null)
const materialFileInput = ref<HTMLInputElement | null>(null)
const activeTab = ref<SpaceTab>('conversas')
const activeFolderFilter = ref<FolderFilter>('all')
const uploadFolderId = ref<string>('')
const newFolderName = ref('')
const newFolderType = ref<SpaceFolder['type']>('folder')
const creatingFolder = ref(false)

const gradient = computed(() => {
  const color = space.value?.color || '#009957'
  return space.value?.cover || `linear-gradient(135deg, ${color} 0%, #1E1E1E 100%)`
})

const filteredChats = computed(() => {
  if (activeFolderFilter.value === 'all') return chats.value
  if (activeFolderFilter.value === 'none') return chats.value.filter((chat) => !chat.folder_id)
  return chats.value.filter((chat) => Number(chat.folder_id) === Number(activeFolderFilter.value))
})

const filteredMaterials = computed(() => {
  if (activeFolderFilter.value === 'all') return materials.value
  if (activeFolderFilter.value === 'none') return materials.value.filter((material) => !material.folder_id)
  return materials.value.filter((material) => Number(material.folder_id) === Number(activeFolderFilter.value))
})

function setTab(tab: string): void {
  activeTab.value = tab as SpaceTab
}

function folderLabel(folderId: number | null): string {
  if (!folderId) return 'Sem pasta'
  return folders.value.find((folder) => folder.id === folderId)?.name ?? 'Pasta'
}

function folderCount(folder: SpaceFolder): string {
  const chatsCount = folder.chats_count ?? chats.value.filter((chat) => chat.folder_id === folder.id).length
  const materialsCount = folder.materials_count ?? materials.value.filter((material) => material.folder_id === folder.id).length
  return `${chatsCount} conversas · ${materialsCount} materiais`
}

function formatDate(date: string | null): string {
  if (!date) return 'Sem data'

  try {
    return new Date(date).toLocaleString('pt-PT', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return 'Sem data'
  }
}

async function loadMaterials(): Promise<void> {
  if (!spaceId.value) return

  try {
    materials.value = await fetchSpaceMaterials(spaceId.value)
  } catch (error) {
    console.error('[TUTS] Falha ao carregar materiais do Espaço.', error)
    materialError.value = 'Não foi possível carregar os materiais deste Espaço.'
  }
}

async function loadFolders(): Promise<void> {
  if (!spaceId.value) return

  try {
    folders.value = await fetchSpaceFolders(spaceId.value)
  } catch (error) {
    console.error('[TUTS] Falha ao carregar organização do Espaço.', error)
    folderError.value = 'Não foi possível carregar as pastas deste Espaço.'
  }
}

async function loadSpace(): Promise<void> {
  loading.value = true
  errorMessage.value = null
  materialError.value = null
  folderError.value = null

  try {
    const response = await fetchSpace(spaceId.value)
    space.value = response.space
    chats.value = response.latest_chats ?? []

    const [allChats] = await Promise.all([
      fetchSpaceConversations(spaceId.value),
      loadMaterials(),
      loadFolders(),
    ])

    chats.value = allChats
  } catch (error) {
    console.error('[TUTS] Falha ao carregar Espaço.', error)
    errorMessage.value = 'Não foi possível carregar este Espaço.'
  } finally {
    loading.value = false
  }
}

async function createConversation(folderId?: number | null): Promise<void> {
  if (!space.value || creatingChat.value) return

  creatingChat.value = true
  errorMessage.value = null

  try {
    const chat = await createSpaceConversation(space.value.id, `Conversa em ${space.value.name}`, folderId ?? null)

    await router.push({
      name: 'chat',
      query: {
        chat_id: String(chat.chat_id),
        context: 'space',
        space_id: String(space.value.id),
        space: space.value.name,
        ...(chat.folder_id ? { folder_id: String(chat.folder_id) } : {}),
      },
    })
  } catch (error) {
    console.error('[TUTS] Falha ao criar conversa no Espaço.', error)
    errorMessage.value = 'Não consegui criar a conversa neste Espaço.'
  } finally {
    creatingChat.value = false
  }
}

function openConversation(chat: SpaceChatSummary): void {
  if (!space.value) return

  router.push({
    name: 'chat',
    query: {
      chat_id: String(chat.chat_id),
      context: 'space',
      space_id: String(space.value.id),
      space: space.value.name,
      ...(chat.folder_id ? { folder_id: String(chat.folder_id) } : {}),
    },
  })
}

async function moveChat(chat: SpaceChatSummary, rawFolderId: string): Promise<void> {
  if (!space.value || movingChatId.value !== null) return

  const folderId = rawFolderId === '' ? null : Number(rawFolderId)
  movingChatId.value = chat.chat_id

  try {
    const updated = await moveSpaceConversationToFolder(space.value.id, chat.chat_id, folderId)
    chats.value = chats.value.map((item) => (item.chat_id === chat.chat_id ? updated : item))
    await loadFolders()
  } catch (error) {
    console.error('[TUTS] Falha ao mover conversa.', error)
    errorMessage.value = 'Não consegui mover essa conversa.'
  } finally {
    movingChatId.value = null
  }
}

function openMaterialPicker(): void {
  materialFileInput.value?.click()
}

async function handleMaterialFileChange(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file || !space.value || uploadingMaterial.value) return

  uploadingMaterial.value = true
  materialError.value = null

  try {
    const uploaded = await uploadSpaceMaterial(
      space.value.id,
      file,
      null,
      uploadFolderId.value ? Number(uploadFolderId.value) : null,
    )

    materials.value = [uploaded, ...materials.value]
    await loadFolders()
  } catch (error) {
    console.error('[TUTS] Falha ao fazer upload do material.', error)
    materialError.value = 'Não consegui carregar esse ficheiro. Confirma o tipo e o tamanho.'
  } finally {
    uploadingMaterial.value = false
    input.value = ''
  }
}

function openMaterial(material: SpaceMaterial): void {
  window.open(material.view_url || material.download_url, '_blank', 'noopener,noreferrer')
}

function downloadMaterial(material: SpaceMaterial): void {
  window.open(material.download_url, '_blank', 'noopener,noreferrer')
}

async function moveMaterial(material: SpaceMaterial, rawFolderId: string): Promise<void> {
  if (!space.value || movingMaterialId.value !== null) return

  const folderId = rawFolderId === '' ? null : Number(rawFolderId)
  movingMaterialId.value = material.id

  try {
    const updated = await moveSpaceMaterialToFolder(space.value.id, material.id, folderId)
    materials.value = materials.value.map((item) => (item.id === material.id ? updated : item))
    await loadFolders()
  } catch (error) {
    console.error('[TUTS] Falha ao mover material.', error)
    materialError.value = 'Não consegui mover esse material.'
  } finally {
    movingMaterialId.value = null
  }
}

async function removeMaterial(material: SpaceMaterial): Promise<void> {
  if (!space.value || deletingMaterialId.value !== null) return

  const confirmed = window.confirm(`Eliminar "${material.original_name}" deste Espaço?`)
  if (!confirmed) return

  deletingMaterialId.value = material.id
  materialError.value = null

  try {
    await deleteSpaceMaterial(space.value.id, material.id)
    materials.value = materials.value.filter((item) => item.id !== material.id)
    await loadFolders()
  } catch (error) {
    console.error('[TUTS] Falha ao eliminar material.', error)
    materialError.value = 'Não consegui eliminar esse material.'
  } finally {
    deletingMaterialId.value = null
  }
}

async function createFolder(): Promise<void> {
  if (!space.value || creatingFolder.value) return

  const name = newFolderName.value.trim()
  if (!name) {
    folderError.value = 'Dá um nome à pasta/tema.'
    return
  }

  creatingFolder.value = true
  folderError.value = null

  try {
    const folder = await createSpaceFolder(space.value.id, {
      name,
      type: newFolderType.value,
      color: '#009957',
    })

    folders.value = [...folders.value, folder]
    newFolderName.value = ''
    newFolderType.value = 'folder'
    activeFolderFilter.value = folder.id
  } catch (error) {
    console.error('[TUTS] Falha ao criar pasta.', error)
    folderError.value = 'Não consegui criar essa pasta.'
  } finally {
    creatingFolder.value = false
  }
}

async function renameFolder(folder: SpaceFolder): Promise<void> {
  if (!space.value) return

  const nextName = window.prompt('Novo nome da pasta/tema:', folder.name)
  if (!nextName || !nextName.trim()) return

  try {
    const updated = await updateSpaceFolder(space.value.id, folder.id, { name: nextName.trim() })
    folders.value = folders.value.map((item) => (item.id === folder.id ? updated : item))
  } catch (error) {
    console.error('[TUTS] Falha ao renomear pasta.', error)
    folderError.value = 'Não consegui renomear essa pasta.'
  }
}

async function removeFolder(folder: SpaceFolder): Promise<void> {
  if (!space.value || deletingFolderId.value !== null) return

  const confirmed = window.confirm(`Eliminar "${folder.name}"? As conversas e materiais ficam no Espaço, mas deixam de estar nesta pasta.`)
  if (!confirmed) return

  deletingFolderId.value = folder.id
  folderError.value = null

  try {
    await deleteSpaceFolder(space.value.id, folder.id)
    folders.value = folders.value.filter((item) => item.id !== folder.id)
    chats.value = chats.value.map((chat) => chat.folder_id === folder.id ? { ...chat, folder_id: null, folder_name: null } : chat)
    materials.value = materials.value.map((material) => material.folder_id === folder.id ? { ...material, folder_id: null, folder_name: null } : material)

    if (activeFolderFilter.value === folder.id) {
      activeFolderFilter.value = 'all'
    }

    if (uploadFolderId.value === String(folder.id)) {
      uploadFolderId.value = ''
    }
  } catch (error) {
    console.error('[TUTS] Falha ao eliminar pasta.', error)
    folderError.value = 'Não consegui eliminar essa pasta.'
  } finally {
    deletingFolderId.value = null
  }
}

onMounted(loadSpace)
</script>

<template>
  <div style="height: 100%; overflow-y: auto; background: #ffffff; padding-bottom: 110px;">
    <div style="max-width: 1200px; margin: 0 auto; padding: 32px 24px;">
      <p v-if="loading" style="font-family: Inter, sans-serif; color: #9E9E9E;">
        A carregar Espaço...
      </p>

      <p v-else-if="errorMessage" style="font-family: Inter, sans-serif; color: #E53E3E;">
        {{ errorMessage }}
      </p>

      <template v-else-if="space">
        <div
          :style="{
            height: '170px',
            borderRadius: '22px',
            position: 'relative',
            overflow: 'hidden',
            display: 'flex',
            flexDirection: 'column',
            justifyContent: 'space-between',
            padding: '24px',
            background: gradient,
            boxSizing: 'border-box',
          }"
        >
          <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.30);" />

          <div style="position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
            <button
              style="display: inline-flex; align-items: center; gap: 7px; font-family: Inter, sans-serif; font-weight: 700; font-size: 13px; color: #ffffff; background: rgba(255,255,255,0.18); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.28); border-radius: 9px; padding: 8px 13px; cursor: pointer;"
              @click="router.push({ name: 'ucs', query: { tab: 'espacos' } })"
            >
              <ArrowLeft :size="14" color="#ffffff" />
              Voltar
            </button>

            <button
              style="display: inline-flex; align-items: center; gap: 8px; font-family: Inter, sans-serif; font-weight: 800; font-size: 13px; color: #1E1E1E; background: #ffffff; border: none; border-radius: 10px; padding: 9px 14px; cursor: pointer;"
              :disabled="creatingChat"
              @click="createConversation(activeFolderFilter !== 'all' && activeFolderFilter !== 'none' ? activeFolderFilter : null)"
            >
              <Plus :size="15" color="#009957" />
              {{ creatingChat ? 'A criar...' : 'Nova conversa' }}
            </button>
          </div>

          <div style="position: relative; z-index: 1;">
            <p style="font-family: Inter, sans-serif; font-weight: 800; font-size: 29px; color: #ffffff; margin: 0 0 6px; line-height: 1.15;">
              {{ space.name }}
            </p>
            <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: rgba(255,255,255,0.84); margin: 0; max-width: 720px; line-height: 1.45;">
              {{ space.description || 'Espaço livre para conversas, materiais pessoais e organização temática.' }}
            </p>
          </div>
        </div>

        <div style="display: flex; border-bottom: 1px solid #E5E5E5; margin-top: 32px; margin-bottom: 22px;">
          <button
            v-for="tab in [
              { id: 'conversas', label: 'Conversas' },
              { id: 'materiais', label: 'Os meus materiais' },
              { id: 'organizacao', label: 'Organização' },
            ]"
            :key="tab.id"
            :style="{
              fontFamily: 'Inter, sans-serif',
              fontWeight: activeTab === tab.id ? 700 : 500,
              fontSize: '14px',
              color: activeTab === tab.id ? '#009957' : '#656966',
              background: 'none',
              border: 'none',
              borderBottom: activeTab === tab.id ? '2px solid #009957' : '2px solid transparent',
              padding: '10px 20px',
              cursor: 'pointer',
              marginBottom: '-1px',
            }"
            @click="setTab(tab.id)"
          >
            {{ tab.label }}
          </button>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px;">
          <button
            :style="{ border: activeFolderFilter === 'all' ? '1px solid #009957' : '1px solid #E5E5E5', color: activeFolderFilter === 'all' ? '#009957' : '#656966', background: '#ffffff', borderRadius: '999px', padding: '8px 12px', fontFamily: 'Inter, sans-serif', fontWeight: 800, fontSize: '12px', cursor: 'pointer' }"
            @click="activeFolderFilter = 'all'"
          >
            Tudo
          </button>
          <button
            :style="{ border: activeFolderFilter === 'none' ? '1px solid #009957' : '1px solid #E5E5E5', color: activeFolderFilter === 'none' ? '#009957' : '#656966', background: '#ffffff', borderRadius: '999px', padding: '8px 12px', fontFamily: 'Inter, sans-serif', fontWeight: 800, fontSize: '12px', cursor: 'pointer' }"
            @click="activeFolderFilter = 'none'"
          >
            Sem pasta
          </button>
          <button
            v-for="folder in folders"
            :key="folder.id"
            :style="{ border: activeFolderFilter === folder.id ? '1px solid #009957' : '1px solid #E5E5E5', color: activeFolderFilter === folder.id ? '#009957' : '#656966', background: '#ffffff', borderRadius: '999px', padding: '8px 12px', fontFamily: 'Inter, sans-serif', fontWeight: 800, fontSize: '12px', cursor: 'pointer' }"
            @click="activeFolderFilter = folder.id"
          >
            {{ folder.name }}
          </button>
        </div>

        <div v-if="activeTab === 'conversas'" style="display: flex; flex-direction: column; gap: 14px;">
          <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 4px;">
            <div>
              <h2 style="font-family: Inter, sans-serif; font-weight: 800; font-size: 19px; color: #1E1E1E; margin: 0 0 4px;">
                Conversas do Espaço
              </h2>
              <p style="font-family: Inter, sans-serif; font-size: 13px; color: #9E9E9E; margin: 0;">
                Histórico de conversas associadas a este contexto.
              </p>
            </div>

            <button
              style="display: inline-flex; align-items: center; gap: 8px; border: none; border-radius: 11px; background: #009957; color: #ffffff; font-family: Inter, sans-serif; font-weight: 800; font-size: 13px; padding: 10px 14px; cursor: pointer;"
              :disabled="creatingChat"
              @click="createConversation(activeFolderFilter !== 'all' && activeFolderFilter !== 'none' ? activeFolderFilter : null)"
            >
              <Plus :size="15" color="#ffffff" />
              Nova conversa
            </button>
          </div>

          <article
            v-for="chat in filteredChats"
            :key="chat.chat_id"
            style="display: flex; align-items: center; gap: 14px; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 15px; padding: 16px; text-align: left;"
          >
            <button
              style="display: flex; align-items: center; gap: 14px; flex: 1; min-width: 0; background: transparent; border: none; cursor: pointer; text-align: left; padding: 0;"
              @click="openConversation(chat)"
            >
              <div style="width: 42px; height: 42px; border-radius: 12px; background: rgba(0,153,87,0.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <MessageCircle :size="19" color="#009957" />
              </div>

              <div style="flex: 1; min-width: 0;">
                <p style="font-family: Inter, sans-serif; font-weight: 800; font-size: 14px; color: #1E1E1E; margin: 0 0 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                  {{ chat.title || 'Conversa sem título' }}
                </p>
                <p style="font-family: Inter, sans-serif; font-size: 12px; color: #9E9E9E; margin: 0;">
                  {{ chat.messages_count ?? 0 }} mensagens · {{ folderLabel(chat.folder_id) }} · Atualizado {{ formatDate(chat.updated_at) }}
                </p>
              </div>
            </button>

            <select
              :value="chat.folder_id ?? ''"
              style="border: 1px solid #E5E5E5; border-radius: 10px; padding: 8px 10px; font-family: Inter, sans-serif; font-size: 12px; color: #656966; background: #ffffff;"
              :disabled="movingChatId === chat.chat_id"
              @change="moveChat(chat, ($event.target as HTMLSelectElement).value)"
            >
              <option value="">Sem pasta</option>
              <option v-for="folder in folders" :key="folder.id" :value="folder.id">
                {{ folder.name }}
              </option>
            </select>

            <MoreVertical :size="16" color="#BDBABA" />
          </article>

          <div
            v-if="filteredChats.length === 0"
            style="border: 1px dashed #E5E5E5; border-radius: 16px; padding: 34px; text-align: center;"
          >
            <MessageCircle :size="30" color="#BDBABA" />
            <p style="font-family: Inter, sans-serif; color: #656966; margin: 12px 0 4px;">
              Ainda não há conversas nesta vista.
            </p>
            <p style="font-family: Inter, sans-serif; color: #BDBABA; font-size: 13px; margin: 0 0 16px;">
              Cria uma conversa para guardar dúvidas e decisões neste contexto.
            </p>
            <button
              style="border: none; border-radius: 10px; background: #009957; color: #ffffff; font-family: Inter, sans-serif; font-weight: 800; font-size: 13px; padding: 10px 14px; cursor: pointer;"
              @click="createConversation(activeFolderFilter !== 'all' && activeFolderFilter !== 'none' ? activeFolderFilter : null)"
            >
              Criar conversa
            </button>
          </div>
        </div>

        <div v-else-if="activeTab === 'materiais'" style="display: flex; flex-direction: column; gap: 16px;">
          <input
            ref="materialFileInput"
            type="file"
            style="display: none;"
            accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.txt,.md,.png,.jpg,.jpeg,.webp,.zip"
            @change="handleMaterialFileChange"
          />

          <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
            <div>
              <h2 style="font-family: Inter, sans-serif; font-weight: 800; font-size: 19px; color: #1E1E1E; margin: 0 0 4px;">
                Os meus materiais
              </h2>
              <p style="font-family: Inter, sans-serif; font-size: 13px; color: #9E9E9E; margin: 0;">
                Upload pessoal de ficheiros ligados a este Espaço.
              </p>
            </div>

            <div style="display: flex; align-items: center; gap: 8px;">
              <select
                v-model="uploadFolderId"
                style="border: 1px solid #E5E5E5; border-radius: 11px; padding: 10px 12px; font-family: Inter, sans-serif; font-size: 12px; color: #656966; background: #ffffff;"
              >
                <option value="">Upload sem pasta</option>
                <option v-for="folder in folders" :key="folder.id" :value="String(folder.id)">
                  {{ folder.name }}
                </option>
              </select>

              <button
                style="display: inline-flex; align-items: center; gap: 8px; border: none; border-radius: 11px; background: #009957; color: #ffffff; font-family: Inter, sans-serif; font-weight: 800; font-size: 13px; padding: 10px 14px; cursor: pointer;"
                :disabled="uploadingMaterial"
                @click="openMaterialPicker"
              >
                <UploadCloud :size="16" color="#ffffff" />
                {{ uploadingMaterial ? 'A carregar...' : 'Upload' }}
              </button>
            </div>
          </div>

          <p v-if="materialError" style="font-family: Inter, sans-serif; font-size: 13px; color: #E53E3E; margin: 0;">
            {{ materialError }}
          </p>

          <div v-if="filteredMaterials.length > 0" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px;">
            <article
              v-for="material in filteredMaterials"
              :key="material.id"
              style="border: 1px solid #E5E5E5; border-radius: 16px; background: #ffffff; padding: 15px; display: flex; flex-direction: column; gap: 12px;"
            >
              <div style="display: flex; gap: 12px; align-items: flex-start;">
                <div style="width: 42px; height: 42px; border-radius: 12px; background: rgba(0,153,87,0.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <FileText :size="20" color="#009957" />
                </div>

                <div style="min-width: 0; flex: 1;">
                  <p style="font-family: Inter, sans-serif; font-weight: 800; font-size: 13px; color: #1E1E1E; margin: 0 0 5px; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    {{ material.original_name }}
                  </p>
                  <p style="font-family: Inter, sans-serif; font-size: 12px; color: #9E9E9E; margin: 0;">
                    {{ material.human_size }} · {{ material.extension?.toUpperCase() || 'FICHEIRO' }} · {{ folderLabel(material.folder_id) }}
                  </p>
                  <p style="font-family: Inter, sans-serif; font-size: 11px; color: #BDBABA; margin: 4px 0 0;">
                    Carregado {{ formatDate(material.created_at) }}
                  </p>
                </div>
              </div>

              <select
                :value="material.folder_id ?? ''"
                style="border: 1px solid #E5E5E5; border-radius: 10px; padding: 8px 10px; font-family: Inter, sans-serif; font-size: 12px; color: #656966; background: #ffffff;"
                :disabled="movingMaterialId === material.id"
                @change="moveMaterial(material, ($event.target as HTMLSelectElement).value)"
              >
                <option value="">Sem pasta</option>
                <option v-for="folder in folders" :key="folder.id" :value="folder.id">
                  {{ folder.name }}
                </option>
              </select>

              <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button
                  title="Visualizar"
                  style="width: 34px; height: 34px; border-radius: 10px; border: 1px solid #E5E5E5; background: #ffffff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;"
                  @click="openMaterial(material)"
                >
                  <Eye :size="15" color="#656966" />
                </button>

                <button
                  title="Download"
                  style="width: 34px; height: 34px; border-radius: 10px; border: 1px solid #E5E5E5; background: #ffffff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;"
                  @click="downloadMaterial(material)"
                >
                  <Download :size="15" color="#656966" />
                </button>

                <button
                  title="Eliminar"
                  style="width: 34px; height: 34px; border-radius: 10px; border: 1px solid #F7C6C6; background: #ffffff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;"
                  :disabled="deletingMaterialId === material.id"
                  @click="removeMaterial(material)"
                >
                  <Trash2 :size="15" color="#E53E3E" />
                </button>
              </div>
            </article>
          </div>

          <div
            v-else
            style="border: 1px dashed #E5E5E5; border-radius: 16px; padding: 34px; text-align: center;"
          >
            <FileText :size="30" color="#BDBABA" />
            <p style="font-family: Inter, sans-serif; color: #656966; margin: 12px 0 4px;">
              Ainda não há materiais nesta vista.
            </p>
            <p style="font-family: Inter, sans-serif; color: #BDBABA; font-size: 13px; margin: 0 0 16px;">
              Carrega resumos, PDFs, imagens ou outros ficheiros úteis para este contexto.
            </p>
            <button
              style="border: none; border-radius: 10px; background: #009957; color: #ffffff; font-family: Inter, sans-serif; font-weight: 800; font-size: 13px; padding: 10px 14px; cursor: pointer;"
              :disabled="uploadingMaterial"
              @click="openMaterialPicker"
            >
              Fazer upload
            </button>
          </div>
        </div>

        <div v-else style="display: grid; grid-template-columns: 360px 1fr; gap: 18px; align-items: start;">
          <section style="border: 1px solid #E5E5E5; border-radius: 18px; padding: 18px; background: #ffffff;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
              <div style="width: 42px; height: 42px; border-radius: 13px; background: rgba(0,153,87,0.08); display: flex; align-items: center; justify-content: center;">
                <FolderPlus :size="20" color="#009957" />
              </div>
              <div>
                <h2 style="font-family: Inter, sans-serif; font-weight: 800; font-size: 17px; color: #1E1E1E; margin: 0;">
                  Nova pasta/tema
                </h2>
                <p style="font-family: Inter, sans-serif; font-size: 12px; color: #9E9E9E; margin: 2px 0 0;">
                  Organiza conversas e materiais.
                </p>
              </div>
            </div>

            <input
              v-model="newFolderName"
              placeholder="Ex: Resumos, Teoria, Exame..."
              style="width: 100%; box-sizing: border-box; border: 1px solid #E5E5E5; border-radius: 12px; padding: 11px 12px; font-family: Inter, sans-serif; font-size: 13px; color: #1E1E1E; margin-bottom: 10px;"
              @keyup.enter="createFolder"
            />

            <select
              v-model="newFolderType"
              style="width: 100%; box-sizing: border-box; border: 1px solid #E5E5E5; border-radius: 12px; padding: 11px 12px; font-family: Inter, sans-serif; font-size: 13px; color: #656966; background: #ffffff; margin-bottom: 12px;"
            >
              <option value="folder">Pasta</option>
              <option value="topic">Tema</option>
              <option value="module">Módulo</option>
              <option value="category">Categoria</option>
            </select>

            <button
              style="width: 100%; border: none; border-radius: 12px; background: #009957; color: #ffffff; font-family: Inter, sans-serif; font-weight: 800; font-size: 13px; padding: 11px 14px; cursor: pointer;"
              :disabled="creatingFolder"
              @click="createFolder"
            >
              {{ creatingFolder ? 'A criar...' : 'Criar pasta' }}
            </button>

            <p v-if="folderError" style="font-family: Inter, sans-serif; font-size: 12px; color: #E53E3E; margin: 12px 0 0;">
              {{ folderError }}
            </p>
          </section>

          <section style="display: flex; flex-direction: column; gap: 12px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
              <div>
                <h2 style="font-family: Inter, sans-serif; font-weight: 800; font-size: 19px; color: #1E1E1E; margin: 0 0 4px;">
                  Organização temática
                </h2>
                <p style="font-family: Inter, sans-serif; font-size: 13px; color: #9E9E9E; margin: 0;">
                  Pastas simples para estruturar conversas e materiais do Espaço.
                </p>
              </div>
            </div>

            <article
              v-for="folder in folders"
              :key="folder.id"
              style="border: 1px solid #E5E5E5; border-radius: 16px; background: #ffffff; padding: 16px; display: flex; align-items: center; gap: 14px;"
            >
              <div style="width: 46px; height: 46px; border-radius: 14px; background: rgba(0,153,87,0.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <Folder :size="22" color="#009957" />
              </div>

              <div style="flex: 1; min-width: 0;">
                <p style="font-family: Inter, sans-serif; font-weight: 800; font-size: 14px; color: #1E1E1E; margin: 0 0 4px;">
                  {{ folder.name }}
                </p>
                <p style="font-family: Inter, sans-serif; font-size: 12px; color: #9E9E9E; margin: 0;">
                  {{ folder.type }} · {{ folderCount(folder) }}
                </p>
              </div>

              <button
                title="Criar conversa nesta pasta"
                style="width: 36px; height: 36px; border-radius: 10px; border: 1px solid #E5E5E5; background: #ffffff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;"
                @click="createConversation(folder.id)"
              >
                <MessageCircle :size="16" color="#656966" />
              </button>

              <button
                title="Renomear"
                style="width: 36px; height: 36px; border-radius: 10px; border: 1px solid #E5E5E5; background: #ffffff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;"
                @click="renameFolder(folder)"
              >
                <Edit3 :size="15" color="#656966" />
              </button>

              <button
                title="Eliminar"
                style="width: 36px; height: 36px; border-radius: 10px; border: 1px solid #F7C6C6; background: #ffffff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;"
                :disabled="deletingFolderId === folder.id"
                @click="removeFolder(folder)"
              >
                <Trash2 :size="15" color="#E53E3E" />
              </button>
            </article>

            <div
              v-if="folders.length === 0"
              style="border: 1px dashed #E5E5E5; border-radius: 16px; padding: 34px; text-align: center;"
            >
              <Folder :size="30" color="#BDBABA" />
              <p style="font-family: Inter, sans-serif; color: #656966; margin: 12px 0 4px;">
                Ainda não há pastas neste Espaço.
              </p>
              <p style="font-family: Inter, sans-serif; color: #BDBABA; font-size: 13px; margin: 0;">
                Cria temas como “Resumos”, “Exame”, “Dúvidas” ou “Projeto”.
              </p>
            </div>
          </section>
        </div>
      </template>
    </div>
  </div>
</template>
