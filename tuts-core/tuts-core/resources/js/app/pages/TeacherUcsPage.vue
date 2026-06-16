<script setup lang="ts">
import { ref, computed } from 'vue'
import {
  Search, Plus, ArrowLeft, Folder, FileText,
  Calendar, UploadCloud, Users,
  Trash2, Edit2, X, ChevronDown, ChevronUp, ChevronRight,
} from '@lucide/vue'
import RoleGuard from '@/app/components/RoleGuard.vue'

// ─── Types ─────────────────────────────────────────────────────────────────────
interface TeacherUC  { id: string; name: string; code: string; students: number; modules: number }
interface UCModule   { id: string; ucId: string; title: string }
interface UCFile     { id: string; ucId: string; moduleId: string | null; name: string; size: string }
interface UCEvent    { id: string; ucId: string; title: string; date: string; type: string }
interface DeleteTarget { type: 'uc' | 'module' | 'event' | 'file'; id: string; label: string }
interface FileEditModalState { id: string; name: string; moduleId: string | null }
interface FileModalState {
  activeTab:               'meus_ficheiros' | 'carregar'
  selectedPersonalFileId:  string | null
  uploadName:              string
  moduleId:                string | null
  path:                    { id: string; name: string }[]
}

// ─── Helpers ───────────────────────────────────────────────────────────────────
function eventDotColor(type: string): string {
  if (type === 'exam')       return '#E53935'
  if (type === 'assignment') return '#F59E0B'
  return '#009957'
}
function eventTypeLabel(type: string): string {
  if (type === 'exam')       return 'Exame'
  if (type === 'assignment') return 'Entrega'
  if (type === 'event')      return 'Evento'
  return 'Outro'
}
function eventTypeBg(type: string): string {
  if (type === 'exam')       return '#FEF2F2'
  if (type === 'assignment') return '#FFFBEB'
  return '#F0FDF4'
}

const iconBtnStyle = {
  background: 'none', border: 'none', cursor: 'pointer', padding: '5px',
  borderRadius: '5px', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
}

// ─── Core data ─────────────────────────────────────────────────────────────────
const ucs = ref<TeacherUC[]>([
  { id: 'uc1', name: 'Redes de Computadores', code: 'EIC0041', students: 142, modules: 2 },
  { id: 'uc2', name: 'Algoritmia e Prog.',     code: 'EIC0012', students: 210, modules: 0 },
])

const modules = ref<UCModule[]>([
  { id: 'm1', ucId: 'uc1', title: 'Módulo 1: Introdução à Teoria dos Grafos' },
  { id: 'm2', ucId: 'uc1', title: 'Módulo 2: Algoritmos de Grafos' },
])

const files = ref<UCFile[]>([
  { id: 'f1', ucId: 'uc1', moduleId: 'm1',  name: 'Slides_Aula01_Grafos.pdf',  size: '2.4 MB' },
  { id: 'f2', ucId: 'uc1', moduleId: 'm1',  name: 'Exercicios_Cap01.pdf',       size: '800 KB' },
  { id: 'f3', ucId: 'uc1', moduleId: 'm2',  name: 'Slides_Aula02_Dijkstra.pdf', size: '3.1 MB' },
  { id: 'f4', ucId: 'uc1', moduleId: null,  name: 'Programa_UC_EIC0041.pdf',    size: '120 KB' },
])

const events = ref<UCEvent[]>([
  { id: 'ev1', ucId: 'uc1', title: 'Exame Redes de Computadores', date: '28 Abr - 09:00', type: 'exam'       },
  { id: 'ev2', ucId: 'uc1', title: 'Entrega Projeto Final',        date: '30 Abr - 23:59', type: 'assignment' },
])

// ─── Navigation ────────────────────────────────────────────────────────────────
const searchQuery   = ref('')
const selectedUc    = ref<TeacherUC | null>(null)
const expandedModules = ref<string[]>(['m1'])

function toggleModule(id: string) {
  expandedModules.value = expandedModules.value.includes(id)
    ? expandedModules.value.filter(m => m !== id)
    : [...expandedModules.value, id]
}

// ─── Personal archive (used in Import Material modal) ──────────────────────────
const personalFolders = ref([
  { id: 'fol_1', parentId: 'root',  name: 'Ano Letivo 2025-2026',  color: '#1E3A8A' },
  { id: 'fol_2', parentId: 'root',  name: 'Testes Antigos',         color: '#F57C00' },
  { id: 'fol_3', parentId: 'fol_1', name: 'Redes de Computadores',  color: '#009957' },
])
const personalFiles = ref([
  { id: 'pf1', folderId: 'root',  name: 'Regulamento_Avaliacao.pdf', size: '834 KB' },
  { id: 'pf2', folderId: 'fol_3', name: 'Resumo_Materia_T1.pdf',     size: '1.1 MB' },
  { id: 'pf3', folderId: 'fol_3', name: 'Projeto_Grupo.pptx',        size: '3.7 MB' },
])

// ─── UC Modal ──────────────────────────────────────────────────────────────────
const ucModal = ref({ isOpen: false, mode: 'create' as 'create' | 'edit', data: { id: '', name: '', code: '' } })
function closeUcModal() { ucModal.value = { isOpen: false, mode: 'create', data: { id: '', name: '', code: '' } } }

function handleSaveUc() {
  const name = ucModal.value.data.name.trim()
  const code = ucModal.value.data.code.trim()
  if (!name || !code) return
  if (ucModal.value.mode === 'create') {
    ucs.value = [...ucs.value, { id: `uc-${Date.now()}`, name, code, students: 0, modules: 0 }]
  } else {
    ucs.value = ucs.value.map(u => u.id === ucModal.value.data.id ? { ...u, name, code } : u)
    if (selectedUc.value?.id === ucModal.value.data.id) {
      selectedUc.value = selectedUc.value ? { ...selectedUc.value, name, code } : null
    }
  }
  closeUcModal()
}

// ─── Module Modal ──────────────────────────────────────────────────────────────
const modModal = ref({ isOpen: false, mode: 'create' as 'create' | 'edit', editId: '', title: '' })
function closeModModal() { modModal.value = { isOpen: false, mode: 'create', editId: '', title: '' } }

function handleSaveModule() {
  if (!modModal.value.title.trim() || !selectedUc.value) return
  if (modModal.value.mode === 'create') {
    const newId = `m-${Date.now()}`
    modules.value = [...modules.value, { id: newId, ucId: selectedUc.value.id, title: modModal.value.title.trim() }]
    ucs.value = ucs.value.map(u => u.id === selectedUc.value!.id ? { ...u, modules: u.modules + 1 } : u)
    expandedModules.value = [...expandedModules.value, newId]
  } else {
    modules.value = modules.value.map(m => m.id === modModal.value.editId ? { ...m, title: modModal.value.title.trim() } : m)
  }
  closeModModal()
}

// ─── Event Modal ───────────────────────────────────────────────────────────────
const eventModal = ref({ isOpen: false, mode: 'create' as 'create' | 'edit', editId: '', title: '', date: '', type: 'exam' })
function closeEventModal() { eventModal.value = { isOpen: false, mode: 'create', editId: '', title: '', date: '', type: 'exam' } }

function handleSaveEvent() {
  if (!eventModal.value.title.trim() || !eventModal.value.date.trim() || !selectedUc.value) return
  if (eventModal.value.mode === 'create') {
    events.value = [...events.value, {
      id:    `ev-${Date.now()}`,
      ucId:  selectedUc.value.id,
      title: eventModal.value.title.trim(),
      date:  eventModal.value.date.trim(),
      type:  eventModal.value.type,
    }]
  } else {
    events.value = events.value.map(e => e.id === eventModal.value.editId
      ? { ...e, title: eventModal.value.title.trim(), date: eventModal.value.date.trim(), type: eventModal.value.type }
      : e
    )
  }
  closeEventModal()
}

// ─── File Import Modal ─────────────────────────────────────────────────────────
const fileModal = ref<FileModalState | null>(null)

function openFileModal() {
  fileModal.value = {
    activeTab:              'meus_ficheiros',
    selectedPersonalFileId: null,
    uploadName:             '',
    moduleId:               null,
    path:                   [{ id: 'root', name: 'O Meu Arquivo' }],
  }
}

function setFileModalTab(tab: 'meus_ficheiros' | 'carregar') {
  if (!fileModal.value) return
  fileModal.value = { ...fileModal.value, activeTab: tab }
}

function navigateIntoFolder(fol: { id: string; name: string }) {
  if (!fileModal.value) return
  fileModal.value = {
    ...fileModal.value,
    path:                   [...fileModal.value.path, { id: fol.id, name: fol.name }],
    selectedPersonalFileId: null,
  }
}

function navigateToBreadcrumb(index: number) {
  if (!fileModal.value) return
  fileModal.value = {
    ...fileModal.value,
    path:                   fileModal.value.path.slice(0, index + 1),
    selectedPersonalFileId: null,
  }
}

function togglePickerFile(fileId: string) {
  if (!fileModal.value) return
  fileModal.value = {
    ...fileModal.value,
    selectedPersonalFileId: fileModal.value.selectedPersonalFileId === fileId ? null : fileId,
  }
}

function handleImportSubmit() {
  if (!selectedUc.value || !fileModal.value) return
  let newFile: UCFile
  if (fileModal.value.activeTab === 'meus_ficheiros') {
    const source = personalFiles.value.find(f => f.id === fileModal.value!.selectedPersonalFileId)
    if (!source) return
    newFile = { id: `f_${Date.now()}`, ucId: selectedUc.value.id, moduleId: fileModal.value.moduleId, name: source.name, size: source.size }
  } else {
    if (!fileModal.value.uploadName.trim()) return
    newFile = { id: `f_${Date.now()}`, ucId: selectedUc.value.id, moduleId: fileModal.value.moduleId, name: fileModal.value.uploadName.trim(), size: '1.0 MB' }
  }
  files.value = [...files.value, newFile]
  fileModal.value = null
}

// ─── File Edit Modal ───────────────────────────────────────────────────────────
const fileEditModal = ref<FileEditModalState | null>(null)

function handleSaveFileEdit() {
  if (!fileEditModal.value || !fileEditModal.value.name.trim()) return
  files.value = files.value.map(f => f.id === fileEditModal.value!.id
    ? { ...f, name: fileEditModal.value!.name.trim(), moduleId: fileEditModal.value!.moduleId }
    : f
  )
  fileEditModal.value = null
}

// ─── Delete ────────────────────────────────────────────────────────────────────
const deleteTarget = ref<DeleteTarget | null>(null)

function handleDelete() {
  if (!deleteTarget.value) return
  switch (deleteTarget.value.type) {
    case 'uc':
      ucs.value     = ucs.value.filter(u => u.id !== deleteTarget.value!.id)
      modules.value = modules.value.filter(m => m.ucId !== deleteTarget.value!.id)
      files.value   = files.value.filter(f => f.ucId !== deleteTarget.value!.id)
      events.value  = events.value.filter(e => e.ucId !== deleteTarget.value!.id)
      if (selectedUc.value?.id === deleteTarget.value.id) selectedUc.value = null
      break
    case 'module':
      modules.value = modules.value.filter(m => m.id !== deleteTarget.value!.id)
      files.value   = files.value.filter(f => f.moduleId !== deleteTarget.value!.id)
      if (selectedUc.value) {
        ucs.value = ucs.value.map(u => u.id === selectedUc.value!.id ? { ...u, modules: Math.max(0, u.modules - 1) } : u)
      }
      break
    case 'event':
      events.value = events.value.filter(e => e.id !== deleteTarget.value!.id)
      break
    case 'file':
      files.value = files.value.filter(f => f.id !== deleteTarget.value!.id)
      break
  }
  deleteTarget.value = null
}

// ─── Derived ───────────────────────────────────────────────────────────────────
const filteredUcs = computed(() => searchQuery.value.trim()
  ? ucs.value.filter(u =>
      u.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      u.code.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
  : ucs.value
)

const currentModules = computed(() => modules.value.filter(m => m.ucId === selectedUc.value?.id))
const currentEvents  = computed(() => events.value.filter(e => e.ucId === selectedUc.value?.id))
const generalFiles   = computed(() => files.value.filter(f => f.ucId === selectedUc.value?.id && f.moduleId === null))

const modalCurrentFolderId = computed(() => {
  if (!fileModal.value) return 'root'
  const path = fileModal.value.path
  return path[path.length - 1]?.id ?? 'root'
})
const modalVisibleFolders = computed(() => personalFolders.value.filter(f => f.parentId === modalCurrentFolderId.value))
const modalVisibleFiles   = computed(() => personalFiles.value.filter(f => f.folderId === modalCurrentFolderId.value))

const isImportDisabled = computed(() => {
  if (!fileModal.value) return true
  return fileModal.value.activeTab === 'meus_ficheiros'
    ? !fileModal.value.selectedPersonalFileId
    : !fileModal.value.uploadName.trim()
})
</script>

<template>
  <RoleGuard required="teacher">
    <!-- ── Scrollable page ── -->
    <div style="height: 100%; overflow-y: auto; padding: 28px 28px 48px; font-family: Inter, sans-serif; background: #ffffff;">

      <!-- ════════════════════════════════════════════════════════
           VIEW 1 — UC List
      ════════════════════════════════════════════════════════ -->
      <template v-if="!selectedUc">
        <!-- Page header -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
          <div>
            <h1 style="font-weight: 700; font-size: 24px; color: #1E1E1E; margin: 0 0 4px; line-height: 1.2;">Gestão de UC's</h1>
            <p style="font-weight: 400; font-size: 13px; color: #9E9E9E; margin: 0;">Gere as unidades curriculares que leccionas</p>
          </div>
          <button
            class="transition-opacity hover:opacity-80"
            style="display: inline-flex; align-items: center; gap: 8px; font-family: Inter, sans-serif; font-weight: 600; font-size: 13px; color: #ffffff; background: #009957; border: none; border-radius: 10px; padding: 10px 18px 10px 14px; cursor: pointer;"
            @click="ucModal = { isOpen: true, mode: 'create', data: { id: '', name: '', code: '' } }"
          >
            <Plus :size="16" :stroke-width="2.2" />
            Adicionar UC
          </button>
        </div>

        <!-- Search -->
        <div style="display: flex; align-items: center; gap: 10px; border: 1px solid #E5E5E5; border-radius: 10px; padding: 9px 14px; background: #ffffff; max-width: 400px; margin-bottom: 24px;">
          <Search :size="15" :stroke-width="1.8" color="#BDBABA" style="flex-shrink: 0;" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Pesquisar UC ou código..."
            style="flex: 1; font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #1E1E1E; background: none; border: none; outline: none;"
          />
        </div>

        <!-- UC Grid -->
        <p v-if="filteredUcs.length === 0" style="font-weight: 400; font-size: 14px; color: #BDBABA; margin: 0;">Nenhuma UC encontrada.</p>
        <div v-else class="grid grid-cols-3 gap-4">
          <div
            v-for="uc in filteredUcs"
            :key="uc.id"
            class="hover:border-[#009957] transition-colors cursor-pointer"
            style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 20px; display: flex; flex-direction: column;"
            @click="selectedUc = uc"
          >
            <div style="display: flex; align-items: flex-start; justify-content: space-between;">
              <div style="width: 42px; height: 42px; border-radius: 11px; background: #EDF9EF; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <Folder :size="20" :stroke-width="1.8" color="#009957" />
              </div>
              <div style="display: flex; align-items: center; gap: 4px; margin-right: -4px;">
                <button
                  class="transition-colors hover:bg-blue-50"
                  :style="iconBtnStyle"
                  title="Editar UC"
                  @click.stop="ucModal = { isOpen: true, mode: 'edit', data: { id: uc.id, name: uc.name, code: uc.code } }"
                >
                  <Edit2 :size="14" :stroke-width="1.8" color="#656966" />
                </button>
                <button
                  class="transition-colors hover:bg-red-50"
                  :style="iconBtnStyle"
                  title="Eliminar UC"
                  @click.stop="deleteTarget = { type: 'uc', id: uc.id, label: uc.name }"
                >
                  <Trash2 :size="14" :stroke-width="1.8" color="#E53935" />
                </button>
              </div>
            </div>

            <p style="font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 14px 0 0; line-height: 1.3;">{{ uc.name }}</p>
            <p style="font-weight: 400; font-size: 12px; color: #9E9E9E; margin: 4px 0 0;">{{ uc.code }}</p>

            <div style="display: flex; gap: 16px; margin-top: 16px; padding-top: 14px; border-top: 1px solid #F0F0F0;">
              <span style="display: flex; align-items: center; gap: 5px; font-size: 12px; color: #656966;">
                <Users :size="13" :stroke-width="1.8" color="#9E9E9E" />
                {{ uc.students }} alunos
              </span>
              <span style="display: flex; align-items: center; gap: 5px; font-size: 12px; color: #656966;">
                <Folder :size="13" :stroke-width="1.8" color="#9E9E9E" />
                {{ modules.filter(m => m.ucId === uc.id).length }} módulos
              </span>
            </div>
          </div>
        </div>
      </template>

      <!-- ════════════════════════════════════════════════════════
           VIEW 2 — UC Detail
      ════════════════════════════════════════════════════════ -->
      <template v-else>
        <!-- Back + heading -->
        <div style="margin-bottom: 24px;">
          <button
            class="transition-colors hover:bg-gray-50"
            style="display: inline-flex; align-items: center; gap: 6px; font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 8px; padding: 7px 14px 7px 10px; cursor: pointer; margin-bottom: 18px;"
            @click="selectedUc = null"
          >
            <ArrowLeft :size="15" :stroke-width="2" />
            Voltar
          </button>

          <h2 style="font-weight: 700; font-size: 24px; color: #1E1E1E; margin: 0; display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap;">
            {{ selectedUc.name }}
            <span style="font-weight: 400; font-size: 14px; color: #9E9E9E;">({{ selectedUc.code }})</span>
          </h2>

          <div style="display: flex; gap: 20px; margin-top: 10px;">
            <span style="display: flex; align-items: center; gap: 5px; font-size: 13px; color: #656966;">
              <Users :size="13" :stroke-width="1.8" color="#9E9E9E" />
              {{ selectedUc.students }} alunos inscritos
            </span>
            <span style="display: flex; align-items: center; gap: 5px; font-size: 13px; color: #656966;">
              <Folder :size="13" :stroke-width="1.8" color="#9E9E9E" />
              {{ currentModules.length }} módulos
            </span>
          </div>
        </div>

        <!-- Two-col layout: 2/3 accordion + 1/3 events -->
        <div class="grid grid-cols-3 gap-6">

          <!-- ── LEFT: Modules accordion (col-span-2) ── -->
          <div class="col-span-2">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
              <h3 style="font-weight: 700; font-size: 18px; color: #1E1E1E; margin: 0;">Módulos e conteúdos</h3>
              <div style="display: flex; align-items: center; gap: 12px;">
                <button
                  class="transition-opacity hover:opacity-70"
                  style="display: inline-flex; align-items: center; gap: 5px; font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #009957; background: none; border: none; cursor: pointer; padding: 0;"
                  @click="modModal = { isOpen: true, mode: 'create', editId: '', title: '' }"
                >
                  <Plus :size="13" :stroke-width="2.2" color="#009957" />
                  Adicionar Módulo
                </button>
                <button
                  class="transition-opacity hover:opacity-80"
                  style="display: inline-flex; align-items: center; gap: 6px; font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #009957; background: none; border: 1px dashed #009957; border-radius: 8px; padding: 6px 12px 6px 10px; cursor: pointer;"
                  @click="openFileModal"
                >
                  <UploadCloud :size="13" :stroke-width="2" color="#009957" />
                  Carregar Ficheiro
                </button>
              </div>
            </div>

            <p v-if="currentModules.length === 0" style="font-weight: 400; font-size: 14px; color: #BDBABA; font-style: italic; margin: 0;">Nenhum módulo criado ainda.</p>

            <!-- Module accordions -->
            <div
              v-for="mod in currentModules"
              :key="mod.id"
              style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 10px; margin-bottom: 12px; overflow: hidden;"
            >
              <div
                class="hover:bg-gray-50 transition-colors"
                style="padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 10px; cursor: pointer; user-select: none;"
                @click="toggleModule(mod.id)"
              >
                <span style="font-weight: 600; font-size: 14px; color: #1E1E1E; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ mod.title }}</span>
                <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;" @click.stop>
                  <button
                    class="transition-colors hover:bg-blue-50"
                    :style="iconBtnStyle"
                    title="Editar módulo"
                    @click="modModal = { isOpen: true, mode: 'edit', editId: mod.id, title: mod.title }"
                  >
                    <Edit2 :size="13" :stroke-width="1.8" color="#656966" />
                  </button>
                  <button
                    class="transition-colors hover:bg-red-50"
                    :style="iconBtnStyle"
                    title="Eliminar módulo"
                    @click="deleteTarget = { type: 'module', id: mod.id, label: mod.title }"
                  >
                    <Trash2 :size="13" :stroke-width="1.8" color="#E53935" />
                  </button>
                  <component :is="expandedModules.includes(mod.id) ? ChevronUp : ChevronDown" :size="16" :stroke-width="1.8" color="#9E9E9E" />
                </div>
              </div>

              <div v-if="expandedModules.includes(mod.id)" style="border-top: 1px solid #E5E5E5; padding: 8px 8px 4px;">
                <p v-if="files.filter(f => f.moduleId === mod.id).length === 0" style="font-size: 12px; color: #BDBABA; font-style: italic; margin: 0; padding: 8px;">Nenhum ficheiro neste módulo.</p>
                <div
                  v-for="file in files.filter(f => f.moduleId === mod.id)"
                  :key="file.id"
                  class="hover:bg-gray-50 transition-colors"
                  style="display: flex; align-items: center; gap: 10px; padding: 8px; border-radius: 7px;"
                >
                  <FileText :size="16" :stroke-width="1.8" color="#009957" style="flex-shrink: 0;" />
                  <div style="flex: 1; min-width: 0;">
                    <p style="font-weight: 500; font-size: 13px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ file.name }}</p>
                    <p style="font-weight: 400; font-size: 11px; color: #9E9E9E; margin: 0;">{{ file.size }}</p>
                  </div>
                  <div style="display: flex; align-items: center; gap: 2px; flex-shrink: 0;">
                    <button class="transition-colors hover:bg-blue-50" :style="iconBtnStyle" title="Editar ficheiro" @click="fileEditModal = { id: file.id, name: file.name, moduleId: file.moduleId }">
                      <Edit2 :size="13" :stroke-width="1.8" color="#9E9E9E" />
                    </button>
                    <button class="transition-colors hover:bg-red-50" :style="iconBtnStyle" title="Eliminar ficheiro" @click="deleteTarget = { type: 'file', id: file.id, label: file.name }">
                      <Trash2 :size="13" :stroke-width="1.8" color="#BDBABA" />
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Geral (root) files -->
            <div v-if="generalFiles.length > 0" :style="{ marginTop: currentModules.length > 0 ? '16px' : '0' }">
              <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                <span style="font-weight: 600; font-size: 12px; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.06em;">Geral</span>
                <div style="flex: 1; height: 1px; background: #F0F0F0;" />
              </div>
              <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 10px; padding: 4px 8px; overflow: hidden;">
                <div
                  v-for="file in generalFiles"
                  :key="file.id"
                  class="hover:bg-gray-50 transition-colors"
                  style="display: flex; align-items: center; gap: 10px; padding: 8px; border-radius: 7px;"
                >
                  <FileText :size="16" :stroke-width="1.8" color="#009957" style="flex-shrink: 0;" />
                  <div style="flex: 1; min-width: 0;">
                    <p style="font-weight: 500; font-size: 13px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ file.name }}</p>
                    <p style="font-weight: 400; font-size: 11px; color: #9E9E9E; margin: 0;">{{ file.size }}</p>
                  </div>
                  <div style="display: flex; align-items: center; gap: 2px; flex-shrink: 0;">
                    <button class="transition-colors hover:bg-blue-50" :style="iconBtnStyle" title="Editar ficheiro" @click="fileEditModal = { id: file.id, name: file.name, moduleId: null }">
                      <Edit2 :size="13" :stroke-width="1.8" color="#9E9E9E" />
                    </button>
                    <button class="transition-colors hover:bg-red-50" :style="iconBtnStyle" title="Eliminar ficheiro" @click="deleteTarget = { type: 'file', id: file.id, label: file.name }">
                      <Trash2 :size="13" :stroke-width="1.8" color="#BDBABA" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── RIGHT: Events widget (col-span-1) ── -->
          <div class="col-span-1">
            <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 20px;">
              <div style="display: flex; align-items: center; justify-content: space-between;" :style="{ marginBottom: currentEvents.length > 0 ? '4px' : '0' }">
                <span style="font-weight: 700; font-size: 14px; color: #1E1E1E;">Próximos Eventos</span>
                <button
                  class="transition-opacity hover:opacity-70"
                  style="display: inline-flex; align-items: center; gap: 5px; font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #009957; background: none; border: none; cursor: pointer; padding: 0;"
                  @click="eventModal = { isOpen: true, mode: 'create', editId: '', title: '', date: '', type: 'exam' }"
                >
                  <Plus :size="12" :stroke-width="2.5" color="#009957" />
                  Novo
                </button>
              </div>

              <p v-if="currentEvents.length === 0" style="font-size: 13px; color: #BDBABA; font-style: italic; margin: 14px 0 0;">Nenhum evento agendado.</p>
              <div v-else style="display: flex; flex-direction: column; gap: 1px; margin-top: 12px;">
                <div
                  v-for="(ev, idx) in currentEvents"
                  :key="ev.id"
                  class="group transition-colors hover:bg-gray-50"
                  style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 6px; border-radius: 8px;"
                  :style="{ borderTop: idx > 0 ? '1px solid #F5F5F5' : 'none' }"
                >
                  <div :style="{ width: '8px', height: '8px', borderRadius: '50%', background: eventDotColor(ev.type), flexShrink: 0, marginTop: '4px' }" />
                  <div style="flex: 1; min-width: 0;">
                    <p style="font-weight: 500; font-size: 13px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ ev.title }}</p>
                    <p style="font-weight: 400; font-size: 11px; color: #9E9E9E; margin: 2px 0 0;">
                      <span :style="{ display: 'inline-block', background: eventTypeBg(ev.type), color: eventDotColor(ev.type), fontSize: '10px', fontWeight: 600, borderRadius: '4px', padding: '1px 5px', marginRight: '5px' }">{{ eventTypeLabel(ev.type) }}</span>
                      {{ ev.date }}
                    </p>
                  </div>
                  <div style="display: flex; align-items: center; gap: 2px; flex-shrink: 0; margin-top: 1px;">
                    <button
                      class="transition-colors hover:bg-blue-50"
                      :style="iconBtnStyle"
                      title="Editar evento"
                      @click="eventModal = { isOpen: true, mode: 'edit', editId: ev.id, title: ev.title, date: ev.date, type: ev.type }"
                    >
                      <Edit2 :size="12" :stroke-width="1.8" color="#9E9E9E" />
                    </button>
                    <button
                      class="transition-colors hover:bg-red-50"
                      :style="iconBtnStyle"
                      title="Eliminar evento"
                      @click="deleteTarget = { type: 'event', id: ev.id, label: ev.title }"
                    >
                      <Trash2 :size="12" :stroke-width="1.8" color="#BDBABA" />
                    </button>
                  </div>
                </div>
              </div>

              <div style="display: flex; align-items: center; gap: 6px; margin-top: 12px; padding: 10px 14px; background: #F4FBF7; border-radius: 10px; border: 1px solid rgba(0,153,87,0.12);">
                <Calendar :size="13" :stroke-width="1.8" color="#009957" />
                <span style="font-size: 12px; color: #009957; font-weight: 500;">{{ currentEvents.length }} evento{{ currentEvents.length !== 1 ? 's' : '' }} agendado{{ currentEvents.length !== 1 ? 's' : '' }}</span>
              </div>
            </div>
          </div>

        </div>
      </template>
    </div>

    <!-- ════════════════════════════════════════════════════════
         MODAL — Delete Confirmation
    ════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="deleteTarget"
        style="position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.4); padding: 24px;"
        @click="deleteTarget = null"
      >
        <div style="background: #ffffff; border-radius: 16px; padding: 24px; box-shadow: 0 8px 40px rgba(0,0,0,0.16); width: 100%; max-width: 460px;" @click.stop>
          <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px;">
            <div style="width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0; background: #FEF2F2; display: flex; align-items: center; justify-content: center;">
              <Trash2 :size="20" :stroke-width="1.8" color="#DC2626" />
            </div>
            <button :style="{ ...iconBtnStyle, padding: '4px' }" @click="deleteTarget = null">
              <X :size="18" :stroke-width="2" color="#9E9E9E" />
            </button>
          </div>
          <h3 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 17px; color: #1E1E1E; margin: 0 0 8px;">Tem a certeza?</h3>
          <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #656966; margin: 0 0 10px; line-height: 1.5;">Esta ação é irreversível.</p>
          <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E; margin: 0 0 24px; background: #F7F7F7; border-radius: 8px; padding: 8px 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">"{{ deleteTarget.label }}"</p>
          <div style="display: flex; gap: 10px;">
            <button class="transition-colors hover:bg-gray-50" style="flex: 1; font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 10px; padding: 10px 0; cursor: pointer;" @click="deleteTarget = null">Cancelar</button>
            <button class="transition-opacity hover:opacity-80" style="flex: 1; font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #ffffff; background: #DC2626; border: none; border-radius: 10px; padding: 10px 0; cursor: pointer;" @click="handleDelete">Eliminar</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════
         MODAL — Add / Edit UC
    ════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="ucModal.isOpen"
        style="position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.4); padding: 24px;"
        @click="closeUcModal"
      >
        <div style="background: #ffffff; border-radius: 16px; padding: 24px; box-shadow: 0 8px 40px rgba(0,0,0,0.16); width: 100%; max-width: 460px;" @click.stop>
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px;">
            <h3 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 17px; color: #1E1E1E; margin: 0;">{{ ucModal.mode === 'create' ? 'Adicionar UC' : 'Editar UC' }}</h3>
            <button :style="{ ...iconBtnStyle, padding: '4px' }" @click="closeUcModal"><X :size="18" :stroke-width="2" color="#9E9E9E" /></button>
          </div>
          <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px;">
            <div style="display: flex; flex-direction: column; gap: 6px;">
              <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966;">Nome da UC *</label>
              <input autofocus v-model="ucModal.data.name" type="text" placeholder="ex: Redes de Computadores" style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #1E1E1E; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; background: #F7F7F7; outline: none; width: 100%; box-sizing: border-box;" />
            </div>
            <div style="display: flex; flex-direction: column; gap: 6px;">
              <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966;">Código da UC *</label>
              <input v-model="ucModal.data.code" type="text" placeholder="ex: EIC0041" style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #1E1E1E; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; background: #F7F7F7; outline: none; width: 100%; box-sizing: border-box;" @keydown.enter="handleSaveUc" />
            </div>
          </div>
          <div style="display: flex; gap: 10px;">
            <button class="transition-colors hover:bg-gray-50" style="flex: 1; font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 10px; padding: 10px 0; cursor: pointer;" @click="closeUcModal">Cancelar</button>
            <button
              class="transition-opacity hover:opacity-80"
              :style="{ flex: 1, fontFamily: 'Inter, sans-serif', fontWeight: 600, fontSize: '14px', color: '#ffffff', background: (ucModal.data.name.trim() && ucModal.data.code.trim()) ? '#009957' : '#C5C5C5', border: 'none', borderRadius: '10px', padding: '10px 0', cursor: (ucModal.data.name.trim() && ucModal.data.code.trim()) ? 'pointer' : 'not-allowed' }"
              @click="handleSaveUc"
            >{{ ucModal.mode === 'create' ? 'Criar UC' : 'Guardar alterações' }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════
         MODAL — Add / Edit Module
    ════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="modModal.isOpen"
        style="position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.4); padding: 24px;"
        @click="closeModModal"
      >
        <div style="background: #ffffff; border-radius: 16px; padding: 24px; box-shadow: 0 8px 40px rgba(0,0,0,0.16); width: 100%; max-width: 460px;" @click.stop>
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px;">
            <h3 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 17px; color: #1E1E1E; margin: 0;">{{ modModal.mode === 'create' ? 'Novo Módulo' : 'Editar Módulo' }}</h3>
            <button :style="{ ...iconBtnStyle, padding: '4px' }" @click="closeModModal"><X :size="18" :stroke-width="2" color="#9E9E9E" /></button>
          </div>
          <div style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 24px;">
            <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966;">Título do módulo *</label>
            <input autofocus v-model="modModal.title" type="text" placeholder="ex: Módulo 3: Camada de Transporte" style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #1E1E1E; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; background: #F7F7F7; outline: none; width: 100%; box-sizing: border-box;" @keydown.enter="handleSaveModule" />
          </div>
          <div style="display: flex; gap: 10px;">
            <button class="transition-colors hover:bg-gray-50" style="flex: 1; font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 10px; padding: 10px 0; cursor: pointer;" @click="closeModModal">Cancelar</button>
            <button
              class="transition-opacity hover:opacity-80"
              :style="{ flex: 1, fontFamily: 'Inter, sans-serif', fontWeight: 600, fontSize: '14px', color: '#ffffff', background: modModal.title.trim() ? '#009957' : '#C5C5C5', border: 'none', borderRadius: '10px', padding: '10px 0', cursor: modModal.title.trim() ? 'pointer' : 'not-allowed' }"
              @click="handleSaveModule"
            >{{ modModal.mode === 'create' ? 'Criar Módulo' : 'Guardar alterações' }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════
         MODAL — Add / Edit Event
    ════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="eventModal.isOpen"
        style="position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.4); padding: 24px;"
        @click="closeEventModal"
      >
        <div style="background: #ffffff; border-radius: 16px; padding: 24px; box-shadow: 0 8px 40px rgba(0,0,0,0.16); width: 100%; max-width: 460px;" @click.stop>
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px;">
            <h3 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 17px; color: #1E1E1E; margin: 0;">{{ eventModal.mode === 'create' ? 'Novo Evento' : 'Editar Evento' }}</h3>
            <button :style="{ ...iconBtnStyle, padding: '4px' }" @click="closeEventModal"><X :size="18" :stroke-width="2" color="#9E9E9E" /></button>
          </div>
          <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px;">
            <div style="display: flex; flex-direction: column; gap: 6px;">
              <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966;">Título do evento *</label>
              <input autofocus v-model="eventModal.title" type="text" placeholder="ex: Exame Intercalar" style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #1E1E1E; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; background: #F7F7F7; outline: none; width: 100%; box-sizing: border-box;" />
            </div>
            <div style="display: flex; flex-direction: column; gap: 6px;">
              <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966;">Data *</label>
              <input v-model="eventModal.date" type="text" placeholder="ex: 28 Abr - 09:00" style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #1E1E1E; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; background: #F7F7F7; outline: none; width: 100%; box-sizing: border-box;" @keydown.enter="handleSaveEvent" />
            </div>
            <div style="display: flex; flex-direction: column; gap: 6px;">
              <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966;">Tipo</label>
              <select v-model="eventModal.type" style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #1E1E1E; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; background: #F7F7F7; outline: none; cursor: pointer; width: 100%; box-sizing: border-box;">
                <option value="exam">Exame</option>
                <option value="assignment">Entrega / Assignment</option>
                <option value="event">Evento</option>
                <option value="other">Outro</option>
              </select>
            </div>
          </div>
          <div style="display: flex; gap: 10px;">
            <button class="transition-colors hover:bg-gray-50" style="flex: 1; font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 10px; padding: 10px 0; cursor: pointer;" @click="closeEventModal">Cancelar</button>
            <button
              class="transition-opacity hover:opacity-80"
              :style="{ flex: 1, fontFamily: 'Inter, sans-serif', fontWeight: 600, fontSize: '14px', color: '#ffffff', background: (eventModal.title.trim() && eventModal.date.trim()) ? '#009957' : '#C5C5C5', border: 'none', borderRadius: '10px', padding: '10px 0', cursor: (eventModal.title.trim() && eventModal.date.trim()) ? 'pointer' : 'not-allowed' }"
              @click="handleSaveEvent"
            >{{ eventModal.mode === 'create' ? 'Guardar Evento' : 'Guardar alterações' }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════
         MODAL — Import Material (hierarchical Drive navigator)
    ════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="fileModal"
        style="position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.45); padding: 24px;"
        @click="fileModal = null"
      >
        <div
          style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 850px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 24px 48px rgba(0,0,0,0.22);"
          @click.stop
        >
          <!-- Header + Tabs -->
          <div style="padding: 24px 24px 0; flex-shrink: 0;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
              <h3 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 18px; color: #1E1E1E; margin: 0;">Importar Material</h3>
              <button :style="{ ...iconBtnStyle, padding: '4px' }" @click="fileModal = null"><X :size="18" :stroke-width="2" color="#9E9E9E" /></button>
            </div>
            <div style="display: flex; border-bottom: 1px solid #E5E5E5;">
              <button
                v-for="tab in [{ key: 'meus_ficheiros', label: 'Os meus materiais' }, { key: 'carregar', label: 'Carregar' }]"
                :key="tab.key"
                :style="{ fontFamily: 'Inter, sans-serif', fontWeight: fileModal.activeTab === tab.key ? 600 : 400, fontSize: '13px', color: fileModal.activeTab === tab.key ? '#009957' : '#9E9E9E', background: 'none', border: 'none', borderBottom: fileModal.activeTab === tab.key ? '2px solid #009957' : '2px solid transparent', padding: '8px 18px', cursor: 'pointer', marginBottom: '-1px', transition: 'color 0.15s' }"
                @click="setFileModalTab(tab.key as 'meus_ficheiros' | 'carregar')"
              >{{ tab.label }}</button>
            </div>
          </div>

          <!-- Scrollable content -->
          <div style="flex: 1; overflow-y: auto; padding: 0 24px 8px; min-height: 320px;">

            <!-- Tab 1: Os meus materiais -->
            <template v-if="fileModal.activeTab === 'meus_ficheiros'">
              <!-- Breadcrumb bar -->
              <div style="position: sticky; top: 0; background: #ffffff; padding-top: 20px; padding-bottom: 12px; border-bottom: 1px solid #F0F0F0; margin-bottom: 16px; display: flex; align-items: center; gap: 4px; flex-wrap: wrap; z-index: 2;">
                <div
                  v-for="(entry, index) in fileModal.path"
                  :key="entry.id"
                  style="display: flex; align-items: center; gap: 4px;"
                >
                  <ChevronRight v-if="index > 0" :size="13" :stroke-width="2" color="#BDBABA" />
                  <button
                    :style="{ fontFamily: 'Inter, sans-serif', fontWeight: index === fileModal.path.length - 1 ? 600 : 400, fontSize: '13px', color: index === fileModal.path.length - 1 ? '#1E1E1E' : '#009957', background: 'none', border: 'none', padding: '2px 4px', borderRadius: '4px', cursor: index === fileModal.path.length - 1 ? 'default' : 'pointer', textDecoration: index === fileModal.path.length - 1 ? 'none' : 'underline', textUnderlineOffset: '2px' }"
                    @click="navigateToBreadcrumb(index)"
                  >{{ entry.name }}</button>
                </div>
              </div>

              <!-- Folders -->
              <div v-if="modalVisibleFolders.length > 0" style="margin-bottom: 20px;">
                <p style="font-family: Inter, sans-serif; font-weight: 600; font-size: 11px; color: #BDBABA; text-transform: uppercase; letter-spacing: 0.07em; margin: 0 0 10px;">Pastas</p>
                <div class="grid grid-cols-4 gap-3">
                  <button
                    v-for="fol in modalVisibleFolders"
                    :key="fol.id"
                    class="transition-colors hover:bg-gray-50 text-left"
                    style="background: #FAFAFA; border: 1px solid #E5E5E5; border-radius: 10px; padding: 12px 14px; display: flex; align-items: center; gap: 10px; cursor: pointer;"
                    @click="navigateIntoFolder(fol)"
                  >
                    <div :style="{ width: '32px', height: '32px', borderRadius: '8px', flexShrink: 0, background: fol.color + '1A', display: 'flex', alignItems: 'center', justifyContent: 'center' }">
                      <Folder :size="16" :stroke-width="1.6" :color="fol.color" />
                    </div>
                    <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #1E1E1E; margin: 0; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ fol.name }}</p>
                  </button>
                </div>
              </div>

              <!-- Files -->
              <div v-if="modalVisibleFiles.length > 0">
                <p style="font-family: Inter, sans-serif; font-weight: 600; font-size: 11px; color: #BDBABA; text-transform: uppercase; letter-spacing: 0.07em; margin: 0 0 10px;">Ficheiros</p>
                <div class="grid grid-cols-3 gap-3" style="padding-bottom: 8px;">
                  <div
                    v-for="file in modalVisibleFiles"
                    :key="file.id"
                    :style="{ background: fileModal.selectedPersonalFileId === file.id ? '#F0FDF4' : '#ffffff', border: `1.5px solid ${fileModal.selectedPersonalFileId === file.id ? '#009957' : '#E5E5E5'}`, borderRadius: '10px', padding: '14px', cursor: 'pointer', display: 'flex', flexDirection: 'column', gap: '8px', transition: 'border-color 0.15s, background 0.15s' }"
                    @click="togglePickerFile(file.id)"
                  >
                    <div :style="{ width: '34px', height: '34px', borderRadius: '8px', flexShrink: 0, background: fileModal.selectedPersonalFileId === file.id ? '#DCFCE7' : '#FFF3F3', display: 'flex', alignItems: 'center', justifyContent: 'center' }">
                      <FileText :size="16" :stroke-width="1.8" :color="fileModal.selectedPersonalFileId === file.id ? '#009957' : '#E53935'" />
                    </div>
                    <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.4;">{{ file.name }}</p>
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                      <span style="font-family: Inter, sans-serif; font-weight: 400; font-size: 11px; color: #9E9E9E;">{{ file.size }}</span>
                      <div v-if="fileModal.selectedPersonalFileId === file.id" style="display: flex; align-items: center; gap: 3px;">
                        <div style="width: 14px; height: 14px; border-radius: 50%; background: #009957; display: flex; align-items: center; justify-content: center;">
                          <svg width="8" height="6" viewBox="0 0 8 6" fill="none"><path d="M1 3L3 5L7 1" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <span style="font-family: Inter, sans-serif; font-weight: 600; font-size: 10px; color: #009957;">Selecionado</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Empty state -->
              <div
                v-if="modalVisibleFolders.length === 0 && modalVisibleFiles.length === 0"
                style="border: 2px dashed #E5E5E5; border-radius: 12px; padding: 36px 24px; margin-top: 4px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; text-align: center;"
              >
                <Folder :size="28" :stroke-width="1.4" color="#BDBABA" />
                <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #9E9E9E; margin: 0;">Esta pasta está vazia</p>
                <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 12px; color: #BDBABA; margin: 0;">Navega para outra pasta ou usa o tab "Carregar" para adicionar ficheiros.</p>
              </div>
            </template>

            <!-- Tab 2: Carregar -->
            <div v-else-if="fileModal.activeTab === 'carregar'" style="display: flex; flex-direction: column; gap: 16px; padding-top: 20px;">
              <div
                class="hover:border-[#009957] transition-colors cursor-pointer"
                style="border: 2px dashed #E5E5E5; border-radius: 12px; background: #FAFAFA; padding: 32px 16px; display: flex; flex-direction: column; align-items: center; gap: 8px;"
              >
                <UploadCloud :size="32" :stroke-width="1.4" color="#BDBABA" />
                <span style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #9E9E9E;">Arraste ficheiros ou clique para selecionar</span>
                <span style="font-family: Inter, sans-serif; font-size: 11px; color: #BDBABA;">PDF, DOCX, PPTX — máx. 50 MB</span>
              </div>
              <div style="display: flex; flex-direction: column; gap: 6px;">
                <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966;">Nome do ficheiro *</label>
                <input
                  autofocus
                  v-model="fileModal.uploadName"
                  type="text"
                  placeholder="ex: Slides_Aula03.pdf"
                  style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #1E1E1E; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; background: #F7F7F7; outline: none; width: 100%; box-sizing: border-box;"
                />
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div style="padding: 16px 24px; border-top: 1px solid #E5E5E5; background: #ffffff; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-shrink: 0;">
            <select
              :value="fileModal.moduleId ?? ''"
              style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #1E1E1E; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; background: #F7F7F7; outline: none; cursor: pointer; flex: 1; max-width: 300px; box-sizing: border-box;"
              @change="(e) => { if (fileModal) fileModal.moduleId = (e.target as HTMLSelectElement).value || null }"
            >
              <option value="">Nenhum (Geral)</option>
              <option v-for="m in currentModules" :key="m.id" :value="m.id">{{ m.title }}</option>
            </select>
            <div style="display: flex; gap: 10px; flex-shrink: 0;">
              <button class="transition-colors hover:bg-gray-50" style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 10px; padding: 10px 20px; cursor: pointer;" @click="fileModal = null">Cancelar</button>
              <button
                class="transition-opacity hover:opacity-80"
                :style="{ fontFamily: 'Inter, sans-serif', fontWeight: 600, fontSize: '14px', color: '#ffffff', background: isImportDisabled ? '#C5C5C5' : '#009957', border: 'none', borderRadius: '10px', padding: '10px 20px', cursor: isImportDisabled ? 'not-allowed' : 'pointer' }"
                @click="handleImportSubmit"
              >Adicionar à UC</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════
         MODAL — Edit File
    ════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="fileEditModal"
        style="position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.4); padding: 24px;"
        @click="fileEditModal = null"
      >
        <div style="background: #ffffff; border-radius: 16px; padding: 24px; box-shadow: 0 8px 40px rgba(0,0,0,0.16); width: 100%; max-width: 460px;" @click.stop>
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px;">
            <h3 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 17px; color: #1E1E1E; margin: 0;">Editar Ficheiro</h3>
            <button :style="{ ...iconBtnStyle, padding: '4px' }" @click="fileEditModal = null"><X :size="18" :stroke-width="2" color="#9E9E9E" /></button>
          </div>
          <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px;">
            <div style="display: flex; flex-direction: column; gap: 6px;">
              <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966;">Nome do ficheiro *</label>
              <input autofocus v-model="fileEditModal.name" type="text" placeholder="ex: Slides_Aula03.pdf" style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #1E1E1E; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; background: #F7F7F7; outline: none; width: 100%; box-sizing: border-box;" @keydown.enter="handleSaveFileEdit" />
            </div>
            <div style="display: flex; flex-direction: column; gap: 6px;">
              <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966;">Atribuir a módulo</label>
              <select
                :value="fileEditModal.moduleId ?? ''"
                style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #1E1E1E; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; background: #F7F7F7; outline: none; cursor: pointer; width: 100%; box-sizing: border-box;"
                @change="(e) => { if (fileEditModal) fileEditModal.moduleId = (e.target as HTMLSelectElement).value || null }"
              >
                <option value="">Nenhum (Geral)</option>
                <option v-for="m in currentModules" :key="m.id" :value="m.id">{{ m.title }}</option>
              </select>
            </div>
          </div>
          <div style="display: flex; gap: 10px;">
            <button class="transition-colors hover:bg-gray-50" style="flex: 1; font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 10px; padding: 10px 0; cursor: pointer;" @click="fileEditModal = null">Cancelar</button>
            <button
              class="transition-opacity hover:opacity-80"
              :style="{ flex: 1, fontFamily: 'Inter, sans-serif', fontWeight: 600, fontSize: '14px', color: '#ffffff', background: fileEditModal.name.trim() ? '#009957' : '#C5C5C5', border: 'none', borderRadius: '10px', padding: '10px 0', cursor: fileEditModal.name.trim() ? 'pointer' : 'not-allowed' }"
              @click="handleSaveFileEdit"
            >Guardar alterações</button>
          </div>
        </div>
      </div>
    </Teleport>
  </RoleGuard>
</template>
