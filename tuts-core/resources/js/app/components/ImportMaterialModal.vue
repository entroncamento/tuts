<script setup lang="ts">
import { ref, computed } from 'vue'
import { FileText, X, UploadCloud, Folder, Check } from '@lucide/vue'

defineOptions({ name: 'ImportMaterialModal' })

// ─── Types ────────────────────────────────────────────────────────────────────
export interface PersonalFile {
  id:   string
  name: string
  size: string
}

type PickerTab  = 'meus' | 'ucs' | 'upload'
type UploadStep = 'dropzone' | 'destination'

// ─── Props / Emits ────────────────────────────────────────────────────────────
const emit = defineEmits<{
  confirm: [files: PersonalFile[]]
  close:   []
}>()

// ─── Mock data ────────────────────────────────────────────────────────────────
const MOCK_DRIVE_FILES: PersonalFile[] = [
  { id: 'd1', name: 'Resumo_Materia_T1.pdf',    size: '834 KB' },
  { id: 'd2', name: 'Exercicios_Praticos.docx', size: '1.1 MB' },
]

const MOCK_UC_FILES = [
  {
    ucName: 'Redes de Computadores',
    files:  [{ id: 'uc1f1', name: 'Slides_Protocolos.pdf', size: '4.2 MB' }],
  },
  {
    ucName: 'Sistemas Operativos',
    files:  [{ id: 'uc3f1', name: 'Resumo_Processos.pdf',  size: '920 KB' }],
  },
]

// Flat pool for selection resolution
const allFiles: PersonalFile[] = [
  ...MOCK_DRIVE_FILES,
  ...MOCK_UC_FILES.flatMap((uc) => uc.files),
]

// ─── State ────────────────────────────────────────────────────────────────────
const pickerTab    = ref<PickerTab>('meus')
const uploadStep   = ref<UploadStep>('dropzone')
const selectedIds  = ref<Set<string>>(new Set())
const uploadedFile = ref<PersonalFile | null>(null)
const isDragging   = ref(false)
const hoveredFileId = ref<string | null>(null)

const fileInputRef = ref<HTMLInputElement | null>(null)

// ─── Selection helpers ────────────────────────────────────────────────────────
function toggleSelect(id: string): void {
  const next = new Set(selectedIds.value)
  next.has(id) ? next.delete(id) : next.add(id)
  selectedIds.value = next
}

// ─── Confirm logic ────────────────────────────────────────────────────────────
const canConfirm = computed(() => {
  if (pickerTab.value === 'upload') {
    return uploadStep.value === 'destination' && uploadedFile.value !== null
  }
  return selectedIds.value.size > 0
})

function handleConfirm(): void {
  if (!canConfirm.value) return
  if (pickerTab.value === 'upload' && uploadedFile.value) {
    emit('confirm', [uploadedFile.value])
  } else {
    emit('confirm', allFiles.filter((f) => selectedIds.value.has(f.id)))
  }
}

// ─── Upload helpers ───────────────────────────────────────────────────────────
function simulateUpload(fileName: string, fileSize: number): void {
  const sizeStr =
    fileSize > 1_048_576
      ? `${(fileSize / 1_048_576).toFixed(1)} MB`
      : `${Math.round(fileSize / 1024)} KB`
  uploadedFile.value = { id: `upload-${Date.now()}`, name: fileName, size: sizeStr }
  uploadStep.value   = 'destination'
}

function handleFileInput(e: Event): void {
  const input = e.target as HTMLInputElement
  const file  = input.files?.[0]
  if (file) simulateUpload(file.name, file.size)
}

function handleDrop(e: DragEvent): void {
  e.preventDefault()
  isDragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) simulateUpload(file.name, file.size)
}

function resetUpload(): void {
  uploadedFile.value = null
  uploadStep.value   = 'dropzone'
}

// ─── Tab labels ───────────────────────────────────────────────────────────────
const TAB_LABELS: { key: PickerTab; label: string }[] = [
  { key: 'meus',   label: 'Meus Ficheiros' },
  { key: 'ucs',    label: 'Das minhas UCs' },
  { key: 'upload', label: 'Upload'         },
]

// ─── File row hover state helpers ─────────────────────────────────────────────
function fileRowBg(id: string): string {
  if (selectedIds.value.has(id)) return 'rgba(0,153,87,0.04)'
  if (hoveredFileId.value === id) return '#FAFAFA'
  return '#ffffff'
}
</script>

<template>
  <!-- Backdrop — click outside to close -->
  <div
    style="position: fixed; inset: 0; z-index: 200; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center;"
    @click="emit('close')"
  >
    <!-- Panel -->
    <div
      style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 520px; box-shadow: 0 24px 64px rgba(0,0,0,0.16); font-family: Inter, sans-serif; display: flex; flex-direction: column; max-height: 82vh;"
      @click.stop
    >

      <!-- ── Header ── -->
      <div style="display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0;">
        <span style="font-weight: 700; font-size: 16px; color: #1E1E1E;">
          Importar Material
        </span>
        <button
          class="transition-colors hover:text-[#1E1E1E]"
          style="background: none; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center; color: #9E9E9E; border-radius: 6px;"
          @click="emit('close')"
        >
          <X :size="18" :stroke-width="2" />
        </button>
      </div>

      <!-- ── Tabs ── -->
      <div style="display: flex; padding: 14px 24px 0; border-bottom: 1px solid #F0F0F0;">
        <button
          v-for="tab in TAB_LABELS"
          :key="tab.key"
          :style="{
            background:    'none',
            border:        'none',
            cursor:        'pointer',
            padding:       '8px 14px',
            fontFamily:    'Inter, sans-serif',
            fontSize:      '13px',
            fontWeight:    500,
            marginBottom:  '-1px',
            color:         pickerTab === tab.key ? '#009957' : '#656966',
            borderBottom: `2px solid ${pickerTab === tab.key ? '#009957' : 'transparent'}`,
            transition:    'color 0.15s',
          }"
          @click="pickerTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- ── Body ── -->
      <div style="flex: 1; overflow-y: auto; padding: 16px 24px; min-height: 260px;">

        <!-- Meus Ficheiros -->
        <div v-if="pickerTab === 'meus'" style="display: flex; flex-direction: column; gap: 6px;">
          <div
            v-for="f in MOCK_DRIVE_FILES"
            :key="f.id"
            :style="{
              display: 'flex', alignItems: 'center', gap: '12px',
              padding: '11px 10px', borderRadius: '10px', cursor: 'pointer',
              border: `1px solid ${selectedIds.has(f.id) ? '#009957' : '#F0F0F0'}`,
              background: fileRowBg(f.id),
              transition: 'all 0.15s',
            }"
            @click="toggleSelect(f.id)"
            @mouseenter="hoveredFileId = f.id"
            @mouseleave="hoveredFileId = null"
          >
            <div style="background: #E6F4EA; padding: 8px; border-radius: 8px; flex-shrink: 0;">
              <FileText :size="16" color="#009957" />
            </div>
            <div style="flex: 1; min-width: 0;">
              <p style="margin: 0; font-size: 13px; font-weight: 500; color: #1E1E1E; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                {{ f.name }}
              </p>
              <p style="margin: 0; font-size: 11px; color: #9E9E9E;">{{ f.size }}</p>
            </div>
            <div
              :style="{
                width: '20px', height: '20px', borderRadius: '6px', flexShrink: '0',
                border: `2px solid ${selectedIds.has(f.id) ? '#009957' : '#E5E5E5'}`,
                background: selectedIds.has(f.id) ? '#009957' : 'transparent',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                transition: 'all 0.15s',
              }"
            >
              <Check v-if="selectedIds.has(f.id)" :size="12" :stroke-width="3" color="#ffffff" />
            </div>
          </div>
        </div>

        <!-- Das minhas UCs -->
        <div v-if="pickerTab === 'ucs'" style="display: flex; flex-direction: column; gap: 20px;">
          <div v-for="uc in MOCK_UC_FILES" :key="uc.ucName">
            <div style="display: flex; align-items: center; gap: 7px; margin-bottom: 8px;">
              <Folder :size="13" color="#009957" />
              <span style="font-size: 11px; font-weight: 600; color: #656966; text-transform: uppercase; letter-spacing: 0.06em;">
                {{ uc.ucName }}
              </span>
            </div>
            <div style="display: flex; flex-direction: column; gap: 6px;">
              <div
                v-for="f in uc.files"
                :key="f.id"
                :style="{
                  display: 'flex', alignItems: 'center', gap: '12px',
                  padding: '11px 10px', borderRadius: '10px', cursor: 'pointer',
                  border: `1px solid ${selectedIds.has(f.id) ? '#009957' : '#F0F0F0'}`,
                  background: fileRowBg(f.id),
                  transition: 'all 0.15s',
                }"
                @click="toggleSelect(f.id)"
                @mouseenter="hoveredFileId = f.id"
                @mouseleave="hoveredFileId = null"
              >
                <div style="background: #E6F4EA; padding: 8px; border-radius: 8px; flex-shrink: 0;">
                  <FileText :size="16" color="#009957" />
                </div>
                <div style="flex: 1; min-width: 0;">
                  <p style="margin: 0; font-size: 13px; font-weight: 500; color: #1E1E1E; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    {{ f.name }}
                  </p>
                  <p style="margin: 0; font-size: 11px; color: #9E9E9E;">{{ f.size }}</p>
                </div>
                <div
                  :style="{
                    width: '20px', height: '20px', borderRadius: '6px', flexShrink: '0',
                    border: `2px solid ${selectedIds.has(f.id) ? '#009957' : '#E5E5E5'}`,
                    background: selectedIds.has(f.id) ? '#009957' : 'transparent',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    transition: 'all 0.15s',
                  }"
                >
                  <Check v-if="selectedIds.has(f.id)" :size="12" :stroke-width="3" color="#ffffff" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Upload — step 1: dropzone -->
        <div
          v-if="pickerTab === 'upload' && uploadStep === 'dropzone'"
          :style="{
            border: `2px dashed ${isDragging ? '#009957' : '#E5E5E5'}`,
            borderRadius: '14px',
            padding: '44px 24px',
            textAlign: 'center',
            cursor: 'pointer',
            background: isDragging ? 'rgba(0,153,87,0.04)' : '#FAFAFA',
            transition: 'all 0.2s',
          }"
          @dragover.prevent="isDragging = true"
          @dragleave="isDragging = false"
          @drop="handleDrop"
          @click="fileInputRef?.click()"
        >
          <div style="display: flex; flex-direction: column; align-items: center; gap: 14px;">
            <div style="background: rgba(0,153,87,0.08); padding: 16px; border-radius: 14px;">
              <UploadCloud :size="30" color="#009957" :stroke-width="1.8" />
            </div>
            <div>
              <p style="margin: 0; font-size: 14px; font-weight: 600; color: #1E1E1E;">
                Arrasta ficheiros para aqui
              </p>
              <p style="margin: 4px 0 0; font-size: 12px; color: #9E9E9E;">
                ou clica para selecionar · PDF, DOCX, PPTX, JPG
              </p>
            </div>
          </div>
          <input
            ref="fileInputRef"
            type="file"
            accept=".pdf,.docx,.pptx,.jpg,.jpeg,.png"
            style="display: none;"
            @change="handleFileInput"
          />
        </div>

        <!-- Upload — step 2: destination confirmation -->
        <div
          v-if="pickerTab === 'upload' && uploadStep === 'destination' && uploadedFile"
          style="display: flex; flex-direction: column; gap: 14px;"
        >
          <div style="display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 10px; border: 1px solid #009957; background: rgba(0,153,87,0.04);">
            <div style="background: #E6F4EA; padding: 8px; border-radius: 8px;">
              <FileText :size="16" color="#009957" />
            </div>
            <div style="flex: 1; min-width: 0;">
              <p style="margin: 0; font-size: 13px; font-weight: 500; color: #1E1E1E; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                {{ uploadedFile.name }}
              </p>
              <p style="margin: 0; font-size: 11px; color: #9E9E9E;">{{ uploadedFile.size }}</p>
            </div>
            <Check :size="16" color="#009957" />
          </div>
          <p style="margin: 0; font-size: 13px; color: #656966; line-height: 1.6;">
            O ficheiro está pronto a ser anexado à tua mensagem. Clica em
            <strong style="color: #1E1E1E;">Confirmar</strong> para continuar.
          </p>
          <button
            style="align-self: flex-start; background: none; border: none; cursor: pointer; padding: 0; font-family: Inter, sans-serif; font-size: 12px; color: #9E9E9E; text-decoration: underline;"
            @click="resetUpload"
          >
            Escolher outro ficheiro
          </button>
        </div>

      </div>

      <!-- ── Footer ── -->
      <div style="padding: 16px 24px; border-top: 1px solid #F0F0F0; display: flex; justify-content: flex-end; gap: 10px;">
        <button
          class="hover:border-[#1E1E1E] transition-colors"
          style="padding: 9px 18px; background: none; border: 1px solid #E5E5E5; border-radius: 8px; cursor: pointer; font-family: Inter, sans-serif; font-size: 13px; font-weight: 500; color: #656966;"
          @click="emit('close')"
        >
          Cancelar
        </button>
        <button
          :disabled="!canConfirm"
          :style="{
            padding:      '9px 18px',
            background:   canConfirm ? '#009957' : '#E5E5E5',
            border:       'none',
            borderRadius: '8px',
            cursor:       canConfirm ? 'pointer' : 'not-allowed',
            fontFamily:   'Inter, sans-serif',
            fontSize:     '13px',
            fontWeight:   600,
            color:        canConfirm ? '#ffffff' : '#9E9E9E',
          }"
          :class="canConfirm ? 'hover:opacity-[0.85] transition-opacity' : ''"
          @click="handleConfirm"
        >
          Confirmar
        </button>
      </div>

    </div>
  </div>
</template>
