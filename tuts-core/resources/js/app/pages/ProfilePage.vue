<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
  Mail, Phone, MapPin, CalendarDays,
  FileText, Search, Plus, UploadCloud,
  Trash2, AlertTriangle, CheckCircle, Shield, Smartphone, Key,
  Moon, Sun, Monitor, Globe, Sliders, Volume2,
  Layout, BookOpen, Calendar, Cpu, Database, HardDrive, Code, Wifi, Zap,
  Bell, Brain, AlertCircle, Book, Video, Users, MessageCircle,
  ChevronDown, ChevronUp, HelpCircle,
  Briefcase, Building, Clock,
  Folder, ChevronRight, FolderPlus, MoreVertical, Edit2, X,
} from '@lucide/vue'
import { useAppRole } from '@/app/composables/useAppRole'

const { role } = useAppRole()

// ─── Nav ───────────────────────────────────────────────────────────────────────
const STUDENT_NAV = ['Perfil', 'Os meus ficheiros', 'Dados pessoais', 'Conta', 'Segurança', 'Definições', 'Configurações', 'Preferências', 'Notificações', 'Ajuda']
const TEACHER_NAV = ['Perfil', 'O Meu Arquivo', 'Dados pessoais', 'Conta', 'Segurança', 'Definições', 'Configurações', 'Preferências', 'Notificações', 'Ajuda']

const IMPLEMENTED = new Set(['Perfil', 'Os meus ficheiros', 'O Meu Arquivo', 'Dados pessoais', 'Conta', 'Segurança', 'Definições', 'Configurações', 'Preferências', 'Notificações', 'Ajuda'])

const activeNav  = ref('Perfil')
const navItems   = computed(() => role.value === 'teacher' ? TEACHER_NAV : STUDENT_NAV)

watch(role, () => { activeNav.value = 'Perfil' })

// ─── Quick info ────────────────────────────────────────────────────────────────
const QUICK_INFO = [
  { icon: Mail,         label: 'Email',    value: 'john.smith@tut.ac.pt' },
  { icon: Phone,        label: 'Telefone', value: '+351 912 345 678'     },
  { icon: MapPin,       label: 'Campus',   value: 'Campus Principal — Lisboa' },
  { icon: CalendarDays, label: 'Inscrito', value: 'Setembro 2023' },
]
const TEACHER_QUICK_INFO = [
  { icon: Mail,      label: 'Email',        value: 'prof.silva@tut.ac.pt' },
  { icon: Phone,     label: 'Telefone',     value: '+351 234 567 890'     },
  { icon: Building,  label: 'Departamento', value: 'DETI'                 },
  { icon: Briefcase, label: 'Cargo',        value: 'Professor Auxiliar'   },
]
const TEACHER_UCS = [
  { id: 't-uc1', name: 'Redes de Computadores', students: 142, role: 'Regente',    color: '#009957' },
  { id: 't-uc2', name: 'Sistemas Distribuídos',  students: 85,  role: 'Assistente', color: '#1E3A8A' },
]
const FAQS = [
  { id: 'faq1', q: 'Como redefino a minha palavra-passe?',        a: 'Acede à tab Conta e clica em Alterar na secção de Credenciais.' },
  { id: 'faq2', q: 'Posso sincronizar dados entre dispositivos?', a: 'Sim, podes ativar a Cloud Sync na tab Configurações.'           },
  { id: 'faq3', q: 'Como desativo notificações?',                 a: 'Usa o interruptor de Notificações Globais nesta página.'        },
]

// ─── Student state ─────────────────────────────────────────────────────────────
interface EnrolledUC { id: string; name: string; convCount: number; color: string }
interface MyFile     { id: string; name: string; size: string; date: string }
interface AppNotif   { id: string; title: string; time: string; type: 'alert' | 'brain' }

const enrolledUcs = ref<EnrolledUC[]>([
  { id: 'uc1', name: 'Redes de Computadores',  convCount: 15, color: '#009957' },
  { id: 'uc2', name: 'Matemática Discreta',    convCount: 8,  color: '#4facfe' },
  { id: 'uc3', name: 'Teoria e Aplicações CS', convCount: 4,  color: '#f093fb' },
])
const myFiles    = ref<MyFile[]>([
  { id: 'f1', name: 'Resumo_M3.pdf',          size: '1.2 MB', date: '24 Abr' },
  { id: 'f2', name: 'Apontamentos_TACS.docx', size: '845 KB', date: '20 Abr' },
])
const fileSearch    = ref('')
const itemToDelete  = ref<{ type: 'uc' | 'file'; id: string } | null>(null)

const totalConvs    = computed(() => Math.max(enrolledUcs.value.reduce((acc, uc) => acc + uc.convCount, 0), 1))
const filteredFiles = computed(() => fileSearch.value.trim()
  ? myFiles.value.filter(f => f.name.toLowerCase().includes(fileSearch.value.toLowerCase()))
  : myFiles.value
)

function handleAddUC() { enrolledUcs.value = [...enrolledUcs.value, { id: `uc-${Date.now()}`, name: 'Nova Unidade Curricular', convCount: 0, color: '#9E9E9E' }] }
function handleUploadFile() { myFiles.value = [...myFiles.value, { id: `f-${Date.now()}`, name: 'Novo_Documento.pdf', size: '2.1 MB', date: 'Hoje' }] }
function confirmDelete() {
  if (!itemToDelete.value) return
  if (itemToDelete.value.type === 'uc')   enrolledUcs.value = enrolledUcs.value.filter(u => u.id !== itemToDelete.value!.id)
  if (itemToDelete.value.type === 'file') myFiles.value     = myFiles.value.filter(f => f.id !== itemToDelete.value!.id)
  itemToDelete.value = null
}

// ─── Teacher archive (Drive) ───────────────────────────────────────────────────
interface DriveFolder { id: string; parentId: string; name: string; color: string }
interface DriveFile   { id: string; folderId: string; name: string; size: string; date: string }
interface PathEntry   { id: string; name: string }

const driveFolders = ref<DriveFolder[]>([
  { id: 'fol_1', parentId: 'root', name: 'Ano Letivo 2025-2026',   color: '#1E3A8A' },
  { id: 'fol_2', parentId: 'root', name: 'Testes Antigos',          color: '#F57C00' },
  { id: 'fol_3', parentId: 'fol_1', name: 'Redes de Computadores', color: '#009957' },
])
const driveFiles = ref<DriveFile[]>([
  { id: 'pf1', folderId: 'root',  name: 'Regulamento_Avaliacao.pdf', size: '834 KB', date: '10 Set' },
  { id: 'pf2', folderId: 'fol_3', name: 'Resumo_Materia_T1.pdf',     size: '1.1 MB', date: '12 Out' },
])
const currentPath = ref<PathEntry[]>([{ id: 'root', name: 'O Meu Arquivo' }])
const currentFolderId = computed(() => currentPath.value[currentPath.value.length - 1].id)

const visibleFolders = computed(() => driveFolders.value.filter(f => f.parentId === currentFolderId.value))
const visibleFiles   = computed(() => driveFiles.value.filter(f => f.folderId === currentFolderId.value))

const renameTarget = ref<{ type: 'folder' | 'file'; id: string; name: string } | null>(null)
const openMenu     = ref<string | null>(null)

function handleCreateFolder() {
  const newId = `fol_${Date.now()}`
  driveFolders.value = [...driveFolders.value, { id: newId, parentId: currentFolderId.value, name: 'Nova Pasta', color: '#009957' }]
}
function handleUploadDriveFile() {
  driveFiles.value = [...driveFiles.value, { id: `pf_${Date.now()}`, folderId: currentFolderId.value, name: 'Novo_Ficheiro.pdf', size: '1.0 MB', date: 'Hoje' }]
}
function getAllDescendantIds(parentId: string): string[] {
  const children = driveFolders.value.filter(f => f.parentId === parentId)
  return children.flatMap(c => [c.id, ...getAllDescendantIds(c.id)])
}
function handleDeleteFolder(id: string) {
  const toDelete = [id, ...getAllDescendantIds(id)]
  driveFolders.value = driveFolders.value.filter(f => !toDelete.includes(f.id))
  driveFiles.value   = driveFiles.value.filter(f => !toDelete.includes(f.folderId))
  openMenu.value = null
}
function handleDeleteDriveFile(id: string) { driveFiles.value = driveFiles.value.filter(f => f.id !== id) }
function handleRenameConfirm() {
  if (!renameTarget.value || !renameTarget.value.name.trim()) return
  if (renameTarget.value.type === 'folder') {
    driveFolders.value = driveFolders.value.map(f => f.id === renameTarget.value!.id ? { ...f, name: renameTarget.value!.name.trim() } : f)
    currentPath.value  = currentPath.value.map(p => p.id === renameTarget.value!.id ? { ...p, name: renameTarget.value!.name.trim() } : p)
  } else {
    driveFiles.value = driveFiles.value.map(f => f.id === renameTarget.value!.id ? { ...f, name: renameTarget.value!.name.trim() } : f)
  }
  renameTarget.value = null
}

// ─── Settings ──────────────────────────────────────────────────────────────────
const theme    = ref('claro')
const viewPref = ref('grelha')
const diffPref = ref('intermedio')

const recentNotifs = ref<AppNotif[]>([
  { id: 'n1', title: 'Trabalho expira amanhã', time: 'há 10 min', type: 'alert' },
  { id: 'n2', title: 'Dica IA: Revê Módulo 3', time: 'há 1 hora', type: 'brain' },
])
const openFaq = ref<string | null>(null)

const quickInfo = computed(() => role.value === 'teacher' ? TEACHER_QUICK_INFO : QUICK_INFO)
</script>

<template>
  <div style="height: 100%; display: flex; overflow: hidden; padding: 24px; gap: 32px; background: #ffffff;">

    <!-- Left: Inner sidebar -->
    <div style="width: 240px; flex-shrink: 0; display: flex; flex-direction: column; gap: 4px; overflow-y: auto;">
      <button
        v-for="item in navItems"
        :key="item"
        :class="item !== activeNav ? 'transition-colors hover:bg-gray-50' : ''"
        :style="{
          display: 'block', width: '100%', textAlign: 'left',
          fontFamily: 'Inter, sans-serif', fontWeight: item === activeNav ? 600 : 400, fontSize: '14px',
          color: item === activeNav ? '#009957' : '#9E9E9E',
          background: item === activeNav ? '#EDF9EF' : 'none',
          border: 'none', borderRadius: '8px',
          padding: '10px 16px', cursor: 'pointer', lineHeight: 1.4,
        }"
        @click="activeNav = item"
      >
        {{ item }}
      </button>
    </div>

    <!-- Right: Main content -->
    <div style="flex: 1; overflow-y: auto; min-width: 0; display: flex; flex-direction: column; gap: 32px; padding-bottom: 24px;">

      <!-- ── TAB: PERFIL ── -->
      <template v-if="activeNav === 'Perfil'">
        <!-- Header -->
        <div style="display: flex; align-items: center; gap: 24px;">
          <div :style="{ width: '80px', height: '80px', borderRadius: '50%', flexShrink: 0, background: role === 'teacher' ? '#1E3A8A' : 'linear-gradient(135deg, #E8EAE6 0%, #D4D8CF 100%)', display: 'flex', alignItems: 'center', justifyContent: 'center', border: role === 'teacher' ? 'none' : '1px solid #E5E5E5' }">
            <span :style="{ fontFamily: 'Inter, sans-serif', fontWeight: 700, fontSize: role === 'teacher' ? '20px' : '26px', color: role === 'teacher' ? '#ffffff' : '#8A9188', letterSpacing: '-0.5px' }">{{ role === 'teacher' ? 'PS' : 'JS' }}</span>
          </div>
          <div style="flex: 1; min-width: 0;">
            <h1 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 24px; color: #1E1E1E; margin: 0 0 4px; line-height: 1.2;">{{ role === 'teacher' ? 'Prof. Silva' : 'John Smith' }}</h1>
            <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 14px; color: #BDBABA; margin: 0;">{{ role === 'teacher' ? 'Docente — Departamento de Eletrónica, Telecomunicações e Informática' : 'Student at TUT University' }}</p>
          </div>
          <button class="transition-opacity hover:opacity-80" style="font-family: Inter, sans-serif; font-weight: 600; font-size: 13px; color: #ffffff; background: #009957; border: none; border-radius: 10px; padding: 10px 20px; cursor: pointer; flex-shrink: 0;">Editar perfil</button>
        </div>

        <section>
          <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Informação rápida</h2>
          <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; display: grid; grid-template-columns: 1fr 1fr;">
            <div v-for="(item, idx) in quickInfo" :key="item.label" :style="{ display: 'flex', alignItems: 'center', gap: '14px', padding: '18px 20px', borderRight: idx % 2 === 1 ? 'none' : '1px solid #F0F0F0', borderBottom: idx >= 2 ? 'none' : '1px solid #F0F0F0' }">
              <div style="width: 36px; height: 36px; border-radius: 9px; background: #F5F5F5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <component :is="item.icon" :size="16" :stroke-width="1.8" color="#656966" />
              </div>
              <div style="min-width: 0;">
                <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 11px; color: #BDBABA; margin: 0 0 2px;">{{ item.label }}</p>
                <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ item.value }}</p>
              </div>
            </div>
          </div>
        </section>

        <!-- UCs section (student) -->
        <section v-if="role === 'student'">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0;">As tuas Unidades Curriculares</h2>
            <button @click="handleAddUC" style="display: inline-flex; align-items: center; gap: 6px; font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #009957; background: rgba(0,153,87,0.07); border: none; border-radius: 8px; padding: 7px 13px; cursor: pointer;">
              <Plus :size="12" :stroke-width="2.5" color="#009957" /> Adicionar UC
            </button>
          </div>
          <p v-if="enrolledUcs.length === 0" style="font-family: Inter, sans-serif; font-size: 14px; color: #BDBABA; margin: 0;">Nenhuma UC inscrita.</p>
          <div v-else style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
            <div v-for="uc in enrolledUcs" :key="uc.id" class="transition-shadow hover:shadow-sm" style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 10px; position: relative;">
              <button @click.stop="itemToDelete = { type: 'uc', id: uc.id }" class="transition-colors hover:bg-red-50" style="position: absolute; top: 12px; right: 12px; background: none; border: none; cursor: pointer; padding: 4px; border-radius: 6px; display: flex; align-items: center;">
                <Trash2 :size="14" :stroke-width="1.8" color="#E53935" />
              </button>
              <div style="display: flex; align-items: center; gap: 10px; padding-right: 22px;">
                <div :style="{ width: '10px', height: '10px', borderRadius: '50%', backgroundColor: uc.color, flexShrink: 0 }" />
                <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ uc.name }}</p>
              </div>
              <p style="font-family: Inter, sans-serif; font-weight: 400; font-size: 12px; color: #9E9E9E; margin: 0;">{{ uc.convCount }} {{ uc.convCount === 1 ? 'conversa' : 'conversas' }}</p>
            </div>
          </div>
        </section>

        <!-- UCs section (teacher) -->
        <section v-else>
          <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Unidades Curriculares Lecionadas</h2>
          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">
            <div v-for="uc in TEACHER_UCS" :key="uc.id" class="transition-shadow hover:shadow-sm" style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 16px; display: flex; flex-direction: column;">
              <div style="display: flex; align-items: center; gap: 10px;">
                <div :style="{ width: '10px', height: '10px', borderRadius: '50%', background: uc.color, flexShrink: 0 }" />
                <p style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ uc.name }}</p>
              </div>
              <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 12px;">
                <span style="font-family: Inter, sans-serif; font-weight: 400; font-size: 12px; color: #9E9E9E;">{{ uc.role }}</span>
                <span style="display: flex; align-items: center; gap: 4px; font-family: Inter, sans-serif; font-weight: 500; font-size: 12px; color: #656966;">
                  <Users :size="12" :stroke-width="1.8" color="#9E9E9E" /> {{ uc.students }} alunos
                </span>
              </div>
            </div>
          </div>
        </section>

        <!-- Study distribution (student) -->
        <section v-if="role === 'student'">
          <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Distribuição de Estudo (Conversas)</h2>
          <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 20px 24px; display: flex; flex-direction: column; gap: 18px;">
            <div v-for="uc in enrolledUcs" :key="uc.id" style="display: flex; align-items: center; gap: 14px;">
              <span style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E; width: 200px; flex-shrink: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ uc.name }}</span>
              <div style="flex: 1; height: 8px; background: #F0F0F0; border-radius: 99px; overflow: hidden;">
                <div :style="{ height: '100%', width: `${Math.round((uc.convCount / totalConvs) * 100)}%`, backgroundColor: uc.color, borderRadius: '99px', transition: 'width 0.4s ease' }" />
              </div>
              <span style="font-family: Inter, sans-serif; font-weight: 600; font-size: 13px; color: #1E1E1E; width: 28px; text-align: right; flex-shrink: 0;">{{ uc.convCount }}</span>
            </div>
          </div>
        </section>

        <!-- Institutional info (teacher) -->
        <section v-else>
          <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Informação Institucional</h2>
          <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 18px 20px; display: flex; flex-direction: column; gap: 14px;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <Building :size="16" :stroke-width="1.8" color="#9E9E9E" style="flex-shrink: 0;" />
              <span style="font-family: Inter, sans-serif; font-size: 14px; color: #1E1E1E;">Gabinete: <strong>4.2.14</strong></span>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
              <Clock :size="16" :stroke-width="1.8" color="#9E9E9E" style="flex-shrink: 0;" />
              <span style="font-family: Inter, sans-serif; font-size: 14px; color: #1E1E1E;">Horário de Atendimento: <strong>Terças e Quintas, 14h–16h</strong></span>
            </div>
          </div>
        </section>
      </template>

      <!-- ── TAB: OS MEUS FICHEIROS ── -->
      <template v-else-if="activeNav === 'Os meus ficheiros'">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
          <h1 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 24px; color: #1E1E1E; margin: 0; white-space: nowrap;">Os meus ficheiros</h1>
          <div style="display: flex; align-items: center; gap: 8px; border: 1px solid #E5E5E5; border-radius: 10px; padding: 8px 12px; background: #ffffff; max-width: 260px; flex: 1;">
            <Search :size="14" :stroke-width="1.8" color="#BDBABA" />
            <input v-model="fileSearch" type="text" placeholder="Pesquisar..." style="flex: 1; font-family: Inter, sans-serif; font-size: 13px; color: #1E1E1E; background: none; border: none; outline: none;" />
          </div>
        </div>
        <div style="background: #F4FBF7; border: 1px solid #D4EFE3; border-radius: 12px; padding: 16px 20px; display: flex; flex-direction: column; gap: 10px;">
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <span style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #1E1E1E;">Armazenamento</span>
            <span style="font-family: Inter, sans-serif; font-size: 13px; color: #656966;">Usado <strong>2.4 GB</strong> de 10 GB</span>
          </div>
          <div style="height: 8px; background: #D4EFE3; border-radius: 99px; overflow: hidden;"><div style="height: 100%; width: 24%; background: #009957; border-radius: 99px;" /></div>
          <span style="font-family: Inter, sans-serif; font-size: 11px; color: #9E9E9E;">7.6 GB disponíveis</span>
        </div>
        <div class="hover:border-[#009957] transition-colors cursor-pointer" style="height: 100px; border: 2px dashed #E5E5E5; border-radius: 12px; background: #FAFAFA; display: flex; align-items: center; justify-content: center; gap: 10px;" @click="handleUploadFile">
          <UploadCloud :size="22" :stroke-width="1.5" color="#BDBABA" />
          <span style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #9E9E9E;">+ Carregar Novo Ficheiro</span>
        </div>
        <p v-if="filteredFiles.length === 0" style="font-family: Inter, sans-serif; font-size: 14px; color: #B0B0B0; margin: 0;">{{ fileSearch ? 'Nenhum ficheiro encontrado.' : 'Nenhum ficheiro. Carrega o teu primeiro documento.' }}</p>
        <div v-else style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
          <div v-for="file in filteredFiles" :key="file.id" class="transition-shadow hover:shadow-sm" style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 10px; position: relative;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between;">
              <div style="width: 40px; height: 40px; border-radius: 10px; background: #FFF3F3; display: flex; align-items: center; justify-content: center;"><FileText :size="20" :stroke-width="1.8" color="#E53935" /></div>
              <button @click.stop="itemToDelete = { type: 'file', id: file.id }" class="transition-colors hover:bg-red-50" style="background: none; border: none; cursor: pointer; padding: 4px; border-radius: 6px; display: flex; align-items: center;"><Trash2 :size="14" :stroke-width="1.8" color="#E53935" /></button>
            </div>
            <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ file.name }}</p>
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <span style="font-family: Inter, sans-serif; font-size: 12px; color: #9E9E9E;">{{ file.size }}</span>
              <span style="font-family: Inter, sans-serif; font-size: 12px; color: #9E9E9E;">{{ file.date }}</span>
            </div>
          </div>
        </div>
      </template>

      <!-- ── TAB: O MEU ARQUIVO (teacher Drive) ── -->
      <template v-else-if="activeNav === 'O Meu Arquivo' && role === 'teacher'">
        <!-- Breadcrumb + actions -->
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
          <nav style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
            <div v-for="(entry, idx) in currentPath" :key="entry.id" style="display: flex; align-items: center; gap: 4px;">
              <ChevronRight v-if="idx > 0" :size="14" :stroke-width="2" color="#BDBABA" />
              <button
                :style="{ fontFamily: 'Inter, sans-serif', fontWeight: idx === currentPath.length - 1 ? 700 : 400, fontSize: idx === currentPath.length - 1 ? '22px' : '14px', color: idx === currentPath.length - 1 ? '#1E1E1E' : '#9E9E9E', background: 'none', border: 'none', padding: 0, cursor: idx === currentPath.length - 1 ? 'default' : 'pointer', lineHeight: 1.2 }"
                @click="currentPath = currentPath.slice(0, idx + 1)"
              >{{ entry.name }}</button>
            </div>
          </nav>
          <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
            <button @click="handleCreateFolder" class="transition-colors hover:bg-gray-50" style="display: inline-flex; align-items: center; gap: 7px; font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 9px; padding: 9px 14px; cursor: pointer;">
              <FolderPlus :size="15" :stroke-width="1.8" color="#009957" /> Nova pasta
            </button>
            <button @click="handleUploadDriveFile" class="transition-opacity hover:opacity-80" style="display: inline-flex; align-items: center; gap: 7px; font-family: Inter, sans-serif; font-weight: 600; font-size: 13px; color: #ffffff; background: #009957; border: none; border-radius: 9px; padding: 9px 14px; cursor: pointer;">
              <UploadCloud :size="15" :stroke-width="2" color="#ffffff" /> Carregar
            </button>
          </div>
        </div>
        <!-- Storage bar -->
        <div style="background: #F4FBF7; border: 1px solid #D4EFE3; border-radius: 12px; padding: 14px 20px; display: flex; flex-direction: column; gap: 8px;">
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <span style="font-family: Inter, sans-serif; font-weight: 600; font-size: 13px; color: #1E1E1E;">Armazenamento</span>
            <span style="font-family: Inter, sans-serif; font-size: 12px; color: #656966;">Usado <strong>1.9 GB</strong> de 20 GB</span>
          </div>
          <div style="height: 6px; background: #D4EFE3; border-radius: 99px; overflow: hidden;"><div style="height: 100%; width: 10%; background: #009957; border-radius: 99px;" /></div>
        </div>
        <!-- Folder grid -->
        <section v-if="visibleFolders.length > 0">
          <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
            <span style="font-family: Inter, sans-serif; font-weight: 600; font-size: 12px; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.06em;">Pastas</span>
            <div style="flex: 1; height: 1px; background: #F0F0F0;" />
          </div>
          <div class="grid grid-cols-4 gap-3">
            <div
              v-for="fol in visibleFolders"
              :key="fol.id"
              class="group transition-colors hover:bg-gray-50"
              style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 14px 16px; cursor: pointer; display: flex; align-items: center; gap: 10px; position: relative;"
              @click="() => { currentPath = [...currentPath, { id: fol.id, name: fol.name }]; openMenu = null }"
            >
              <div :style="{ width: '36px', height: '36px', borderRadius: '9px', flexShrink: 0, background: `${fol.color}18`, display: 'flex', alignItems: 'center', justifyContent: 'center' }">
                <Folder :size="18" :stroke-width="1.6" :color="fol.color" />
              </div>
              <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E; margin: 0; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ fol.name }}</p>
              <button @click.stop="openMenu = openMenu === fol.id ? null : fol.id" class="opacity-0 group-hover:opacity-100 transition-opacity hover:bg-gray-100" style="background: none; border: none; cursor: pointer; padding: 4px; border-radius: 5px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <MoreVertical :size="14" :stroke-width="1.8" color="#9E9E9E" />
              </button>
              <div v-if="openMenu === fol.id" style="position: absolute; top: 100%; right: 8px; z-index: 50; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 10px; box-shadow: 0 4px 16px rgba(0,0,0,0.10); padding: 6px; min-width: 140px;" @click.stop>
                <button @click="() => { renameTarget = { type: 'folder', id: fol.id, name: fol.name }; openMenu = null }" class="transition-colors hover:bg-gray-50" style="display: flex; align-items: center; gap: 8px; width: 100%; background: none; border: none; font-family: Inter, sans-serif; font-size: 13px; color: #1E1E1E; padding: 8px 10px; border-radius: 7px; cursor: pointer;">
                  <Edit2 :size="13" :stroke-width="1.8" color="#656966" /> Renomear
                </button>
                <button @click="handleDeleteFolder(fol.id)" class="transition-colors hover:bg-red-50" style="display: flex; align-items: center; gap: 8px; width: 100%; background: none; border: none; font-family: Inter, sans-serif; font-size: 13px; color: #DC2626; padding: 8px 10px; border-radius: 7px; cursor: pointer;">
                  <Trash2 :size="13" :stroke-width="1.8" color="#DC2626" /> Eliminar
                </button>
              </div>
            </div>
          </div>
        </section>
        <!-- File grid -->
        <section v-if="visibleFiles.length > 0">
          <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
            <span style="font-family: Inter, sans-serif; font-weight: 600; font-size: 12px; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.06em;">Ficheiros</span>
            <div style="flex: 1; height: 1px; background: #F0F0F0;" />
          </div>
          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px;">
            <div v-for="file in visibleFiles" :key="file.id" class="group transition-shadow hover:shadow-sm" style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 10px;">
              <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: #FFF3F3; display: flex; align-items: center; justify-content: center;"><FileText :size="20" :stroke-width="1.8" color="#E53935" /></div>
                <div style="display: flex; gap: 2px;">
                  <button @click="renameTarget = { type: 'file', id: file.id, name: file.name }" class="opacity-0 group-hover:opacity-100 transition-opacity hover:bg-blue-50" style="background: none; border: none; cursor: pointer; padding: 4px; border-radius: 5px; display: flex; align-items: center;" title="Renomear"><Edit2 :size="13" :stroke-width="1.8" color="#9E9E9E" /></button>
                  <button @click="handleDeleteDriveFile(file.id)" class="opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-50" style="background: none; border: none; cursor: pointer; padding: 4px; border-radius: 5px; display: flex; align-items: center;" title="Eliminar"><Trash2 :size="13" :stroke-width="1.8" color="#E53935" /></button>
                </div>
              </div>
              <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ file.name }}</p>
              <div style="display: flex; align-items: center; justify-content: space-between;">
                <span style="font-family: Inter, sans-serif; font-size: 11px; color: #9E9E9E;">{{ file.size }}</span>
                <span style="font-family: Inter, sans-serif; font-size: 11px; color: #BDBABA;">{{ file.date }}</span>
              </div>
            </div>
          </div>
        </section>
        <!-- Empty state -->
        <div v-if="visibleFolders.length === 0 && visibleFiles.length === 0" style="border: 2px dashed #E5E5E5; border-radius: 16px; padding: 48px 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; text-align: center;">
          <div style="width: 52px; height: 52px; border-radius: 13px; background: #F5F5F5; display: flex; align-items: center; justify-content: center;"><Folder :size="24" :stroke-width="1.5" color="#BDBABA" /></div>
          <p style="font-family: Inter, sans-serif; font-weight: 600; font-size: 15px; color: #1E1E1E; margin: 0;">Esta pasta está vazia</p>
          <p style="font-family: Inter, sans-serif; font-size: 13px; color: #BDBABA; margin: 0;">Cria uma nova pasta ou carrega um ficheiro para começar.</p>
        </div>
        <!-- Close folder menu overlay -->
        <div v-if="openMenu" style="position: fixed; inset: 0; z-index: 40;" @click="openMenu = null" />
      </template>

      <!-- ── TAB: DADOS PESSOAIS ── -->
      <template v-else-if="activeNav === 'Dados pessoais'">
        <div style="max-width: 800px; display: flex; flex-direction: column; gap: 32px; margin-top: 6px;">
          <div>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Dados pessoais</h2>
            <p style="font-family: Inter, sans-serif; font-size: 14px; color: #9E9E9E; margin: 0;">Gerir os teus dados pessoais e informação de contacto</p>
          </div>
          <div v-for="card in [{ title: 'Informação básica', fields: [['Primeiro nome','John'],['Apelido','Smith'],['Data de nascimento','2003-05-15','date'],['Nacionalidade','Sul-africana']] }, { title: 'Informação de contacto', fields: [['Email','john.smith@tut.ac.pt'],['Telemóvel','+351 912 345 678']] }, { title: 'Detalhes académicos', fields: [['Nº Estudante','2023010234','text',true],['Faculdade','Engenharia Informática'],['Curso','Licenciatura em Informática'],['Ano curricular','2.º Ano']] }]" :key="card.title">
            <h3 style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #1E1E1E; margin: 0 0 14px;">{{ card.title }}</h3>
            <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 20px;">
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div v-for="([label, val, type, disabled]) in card.fields" :key="label" style="display: flex; flex-direction: column; gap: 6px;">
                  <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966;">{{ label }}</label>
                  <input :type="(type as string) || 'text'" :defaultValue="val" :disabled="!!disabled" :style="{ fontFamily: 'Inter, sans-serif', fontSize: '14px', color: disabled ? '#BDBABA' : '#1E1E1E', border: '1px solid #E5E5E5', borderRadius: '8px', padding: '8px 12px', background: disabled ? '#F9F9F9' : '#F7F7F7', outline: 'none', cursor: disabled ? 'not-allowed' : 'text', width: '100%', boxSizing: 'border-box' }" />
                </div>
              </div>
            </div>
          </div>
          <div><button class="transition-opacity hover:opacity-80" style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #ffffff; background: #009957; border: none; border-radius: 8px; padding: 10px 20px; cursor: pointer;">Guardar alterações</button></div>
        </div>
      </template>

      <!-- ── TAB: CONTA ── -->
      <template v-else-if="activeNav === 'Conta'">
        <div style="max-width: 800px; display: flex; flex-direction: column; gap: 32px; margin-top: 6px;">
          <div>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Conta</h2>
            <p style="font-family: Inter, sans-serif; font-size: 14px; color: #9E9E9E; margin: 0;">Gerir credenciais e métodos de acesso</p>
          </div>
          <section>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Credenciais de login</h2>
            <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; overflow: hidden;">
              <div style="display: flex; align-items: center; padding: 16px 20px; gap: 14px;">
                <div style="width: 36px; height: 36px; border-radius: 9px; background: #F5F5F5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><Mail :size="16" :stroke-width="1.8" color="#656966" /></div>
                <div style="flex: 1;"><p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #1E1E1E; margin: 0;">Email</p><p style="font-family: Inter, sans-serif; font-size: 12px; color: #BDBABA; margin: 0;">john.smith@tut.ac.pt</p></div>
                <button style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 8px; padding: 6px 14px; cursor: pointer; flex-shrink: 0;">Atualizar</button>
              </div>
              <div style="height: 1px; background: #F0F0F0;" />
              <div style="display: flex; align-items: center; padding: 16px 20px; gap: 14px;">
                <div style="width: 36px; height: 36px; border-radius: 9px; background: #F5F5F5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><Key :size="16" :stroke-width="1.8" color="#656966" /></div>
                <div style="flex: 1;"><p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #1E1E1E; margin: 0;">Palavra-passe</p><p style="font-family: Inter, sans-serif; font-size: 12px; color: #BDBABA; margin: 0;">••••••••••••</p></div>
                <button style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 8px; padding: 6px 14px; cursor: pointer; flex-shrink: 0;">Alterar</button>
              </div>
            </div>
          </section>
          <section>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Zona de perigo</h2>
            <div style="border: 1px solid #FECACA; background: #FEF2F2; border-radius: 12px; padding: 20px; display: flex; align-items: center; justify-content: space-between; gap: 20px;">
              <div style="display: flex; align-items: flex-start; gap: 14px; flex: 1;">
                <AlertTriangle :size="22" :stroke-width="1.8" color="#DC2626" style="flex-shrink: 0; margin-top: 2px;" />
                <div>
                  <p style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #DC2626; margin: 0 0 4px;">Eliminar conta</p>
                  <p style="font-family: Inter, sans-serif; font-size: 13px; color: #9E9E9E; margin: 0; line-height: 1.5;">A eliminação da conta é permanente e irreversível.</p>
                </div>
              </div>
              <button class="transition-opacity hover:opacity-80" style="font-family: Inter, sans-serif; font-weight: 600; font-size: 13px; color: #ffffff; background: #DC2626; border: none; border-radius: 8px; padding: 10px 16px; cursor: pointer; flex-shrink: 0;">Eliminar a minha conta</button>
            </div>
          </section>
        </div>
      </template>

      <!-- ── TAB: SEGURANÇA ── -->
      <template v-else-if="activeNav === 'Segurança'">
        <div style="max-width: 800px; display: flex; flex-direction: column; gap: 32px; margin-top: 6px;">
          <div>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Segurança</h2>
            <p style="font-family: Inter, sans-serif; font-size: 14px; color: #9E9E9E; margin: 0;">Protege a tua conta com funcionalidades avançadas</p>
          </div>
          <div style="background: #EDF9EF; border: 1px solid #009957; border-radius: 12px; padding: 16px; display: flex; align-items: center; gap: 12px;">
            <CheckCircle :size="22" :stroke-width="1.8" color="#009957" style="flex-shrink: 0;" />
            <p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #009957; margin: 0; line-height: 1.5;">A tua conta está segura. Todas as funcionalidades recomendadas estão ativas.</p>
          </div>
          <section>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Autenticação de dois fatores (2FA)</h2>
            <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; overflow: hidden;">
              <div style="display: flex; align-items: center; padding: 16px 20px; gap: 14px;">
                <div style="width: 36px; height: 36px; border-radius: 9px; background: #F5F5F5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><Shield :size="16" :stroke-width="1.8" color="#009957" /></div>
                <div style="flex: 1;"><p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #1E1E1E; margin: 0;">App de autenticação</p><p style="font-family: Inter, sans-serif; font-size: 12px; color: #BDBABA; margin: 0;">Google Authenticator ou similar</p></div>
                <span style="font-family: Inter, sans-serif; font-weight: 600; font-size: 12px; color: #009957; background: #EDF9EF; border-radius: 6px; padding: 4px 10px; flex-shrink: 0;">Ativo</span>
              </div>
              <div style="height: 1px; background: #F0F0F0;" />
              <div style="display: flex; align-items: center; padding: 16px 20px; gap: 14px;">
                <div style="width: 36px; height: 36px; border-radius: 9px; background: #F5F5F5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><Smartphone :size="16" :stroke-width="1.8" color="#656966" /></div>
                <div style="flex: 1;"><p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #1E1E1E; margin: 0;">Autenticação por SMS</p><p style="font-family: Inter, sans-serif; font-size: 12px; color: #BDBABA; margin: 0;">Não configurada</p></div>
                <button style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 8px; padding: 6px 14px; cursor: pointer; flex-shrink: 0;">Configurar</button>
              </div>
            </div>
          </section>
        </div>
      </template>

      <!-- ── TAB: DEFINIÇÕES ── -->
      <template v-else-if="activeNav === 'Definições'">
        <div style="max-width: 800px; display: flex; flex-direction: column; gap: 32px; margin-top: 6px;">
          <div>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Definições</h2>
            <p style="font-family: Inter, sans-serif; font-size: 14px; color: #9E9E9E; margin: 0;">Personaliza a tua experiência na TUT'S</p>
          </div>
          <div>
            <h3 style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #1E1E1E; margin: 0 0 14px;">Aparência</h3>
            <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 20px;">
              <div style="margin-bottom: 24px;">
                <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; display: block; margin-bottom: 10px;">Tema</label>
                <div style="display: flex; gap: 8px;">
                  <button v-for="t in [{ id: 'claro', label: 'Claro', icon: Sun }, { id: 'escuro', label: 'Escuro', icon: Moon }, { id: 'sistema', label: 'Sistema', icon: Monitor }]" :key="t.id" @click="theme = t.id" :style="{ display: 'inline-flex', alignItems: 'center', gap: '8px', fontFamily: 'Inter, sans-serif', fontWeight: 500, fontSize: '13px', color: theme === t.id ? '#009957' : '#656966', background: theme === t.id ? '#EDF9EF' : '#F7F7F7', border: `1px solid ${theme === t.id ? '#009957' : '#E5E5E5'}`, borderRadius: '8px', padding: '9px 16px', cursor: 'pointer' }">
                    <component :is="t.icon" :size="15" :stroke-width="1.8" :color="theme === t.id ? '#009957' : '#656966'" /> {{ t.label }}
                  </button>
                </div>
              </div>
              <div>
                <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; display: flex; align-items: center; gap: 6px; margin-bottom: 10px;">
                  <Sliders :size="14" :stroke-width="1.8" color="#656966" /> Tamanho da fonte
                </label>
                <div style="display: flex; align-items: center; gap: 12px;">
                  <span style="font-family: Inter, sans-serif; font-size: 11px; color: #BDBABA;">A</span>
                  <input type="range" min="12" max="20" defaultValue="16" class="accent-[#009957]" style="flex: 1;" />
                  <span style="font-family: Inter, sans-serif; font-size: 16px; color: #BDBABA;">A</span>
                </div>
              </div>
            </div>
          </div>
          <section>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Dados e armazenamento</h2>
            <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; overflow: hidden;">
              <div v-for="(row, idx) in [{ icon: Database, label: 'Cache da aplicação', value: '124 MB ocupados', btn: 'Limpar' }, { icon: HardDrive, label: 'Descarregar dados', value: 'Exportar todos os teus dados', btn: 'Exportar' }, { icon: Wifi, label: 'Dados offline', value: '256 MB em cache local', btn: 'Gerir' }]" :key="row.label">
                <div v-if="idx > 0" style="height: 1px; background: #F0F0F0;" />
                <div style="display: flex; align-items: center; padding: 16px 20px; gap: 14px;">
                  <div style="width: 36px; height: 36px; border-radius: 9px; background: #F5F5F5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><component :is="row.icon" :size="16" :stroke-width="1.8" color="#656966" /></div>
                  <div style="flex: 1;"><p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #1E1E1E; margin: 0;">{{ row.label }}</p><p style="font-family: Inter, sans-serif; font-size: 12px; color: #BDBABA; margin: 0;">{{ row.value }}</p></div>
                  <button style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 8px; padding: 6px 14px; cursor: pointer; flex-shrink: 0;">{{ row.btn }}</button>
                </div>
              </div>
            </div>
          </section>
        </div>
      </template>

      <!-- ── TAB: CONFIGURAÇÕES ── -->
      <template v-else-if="activeNav === 'Configurações'">
        <div style="max-width: 800px; display: flex; flex-direction: column; gap: 32px; margin-top: 6px;">
          <div>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Configurações</h2>
            <p style="font-family: Inter, sans-serif; font-size: 14px; color: #9E9E9E; margin: 0;">Definições avançadas do sistema e integrações</p>
          </div>
          <div>
            <h3 style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #1E1E1E; margin: 0 0 14px;">IA e funcionalidades inteligentes</h3>
            <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 20px;">
              <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 18px;">
                <label v-for="lbl in ['Assistente de estudo com IA', 'Recomendações personalizadas de conteúdo', 'Agendamento automático inteligente']" :key="lbl" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                  <input type="checkbox" checked class="accent-[#009957]" style="width: 16px; height: 16px; cursor: pointer; flex-shrink: 0;" />
                  <span style="font-family: Inter, sans-serif; font-size: 14px; color: #1E1E1E;">{{ lbl }}</span>
                </label>
              </div>
              <div style="display: flex; align-items: center; gap: 10px; background: #F7F7F7; border-radius: 8px; padding: 10px 14px;">
                <Cpu :size="15" :stroke-width="1.8" color="#009957" />
                <span style="font-family: Inter, sans-serif; font-size: 13px; color: #656966;">Atualmente a usar: <strong style="color: #1E1E1E;">GPT-4 Enhanced</strong></span>
              </div>
            </div>
          </div>
          <section>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Desempenho</h2>
            <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 20px 24px; display: flex; flex-direction: column; gap: 18px;">
              <div v-for="{ label, pct, color } in [{ label: 'Desempenho do sistema', pct: 92, color: '#009957' }, { label: 'Rede e conectividade', pct: 100, color: '#4facfe' }, { label: 'Tempo de resposta IA', pct: 88, color: '#f093fb' }]" :key="label" style="display: flex; align-items: center; gap: 14px;">
                <span style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E; width: 220px; flex-shrink: 0;">{{ label }}</span>
                <div style="flex: 1; height: 8px; background: #F0F0F0; border-radius: 99px; overflow: hidden;"><div :style="{ height: '100%', width: `${pct}%`, backgroundColor: color, borderRadius: '99px' }" /></div>
                <span style="font-family: Inter, sans-serif; font-weight: 600; font-size: 12px; color: #1E1E1E; width: 36px; text-align: right; flex-shrink: 0;">{{ pct }}%</span>
              </div>
              <div style="display: flex; align-items: center; gap: 6px; padding-top: 4px;">
                <Zap :size="13" :stroke-width="1.8" color="#009957" />
                <span style="font-family: Inter, sans-serif; font-size: 12px; color: #9E9E9E;">Métricas em tempo real — atualizado há 30 segundos</span>
              </div>
            </div>
          </section>
        </div>
      </template>

      <!-- ── TAB: PREFERÊNCIAS ── -->
      <template v-else-if="activeNav === 'Preferências'">
        <div style="max-width: 800px; display: flex; flex-direction: column; gap: 32px; margin-top: 6px;">
          <div>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Preferências</h2>
            <p style="font-family: Inter, sans-serif; font-size: 14px; color: #9E9E9E; margin: 0;">Personaliza a tua experiência de estudo</p>
          </div>
          <div>
            <h3 style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #1E1E1E; margin: 0 0 14px;">Visualização</h3>
            <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 20px;">
              <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; display: block; margin-bottom: 10px;">Vista predefinida</label>
              <div style="display: flex; gap: 8px;">
                <button v-for="v in [{ id: 'grelha', label: 'Grelha', icon: Layout }, { id: 'lista', label: 'Lista', icon: BookOpen }, { id: 'calendario', label: 'Calendário', icon: Calendar }]" :key="v.id" @click="viewPref = v.id" :style="{ display: 'inline-flex', alignItems: 'center', gap: '8px', fontFamily: 'Inter, sans-serif', fontWeight: 500, fontSize: '13px', color: viewPref === v.id ? '#009957' : '#656966', background: viewPref === v.id ? '#EDF9EF' : '#F7F7F7', border: `1px solid ${viewPref === v.id ? '#009957' : '#E5E5E5'}`, borderRadius: '8px', padding: '9px 16px', cursor: 'pointer' }">
                  <component :is="v.icon" :size="15" :stroke-width="1.8" :color="viewPref === v.id ? '#009957' : '#656966'" /> {{ v.label }}
                </button>
              </div>
            </div>
          </div>
          <div>
            <h3 style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #1E1E1E; margin: 0 0 14px;">Conteúdo</h3>
            <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 20px;">
              <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; display: block; margin-bottom: 10px;">Nível de dificuldade preferido</label>
              <div style="display: flex; gap: 8px;">
                <button v-for="d in [{ id: 'iniciante', label: 'Iniciante' }, { id: 'intermedio', label: 'Intermédio' }, { id: 'avancado', label: 'Avançado' }]" :key="d.id" @click="diffPref = d.id" :style="{ fontFamily: 'Inter, sans-serif', fontWeight: 500, fontSize: '13px', color: diffPref === d.id ? '#ffffff' : '#656966', background: diffPref === d.id ? '#009957' : '#F7F7F7', border: `1px solid ${diffPref === d.id ? '#009957' : '#E5E5E5'}`, borderRadius: '8px', padding: '9px 18px', cursor: 'pointer' }">{{ d.label }}</button>
              </div>
            </div>
          </div>
          <div>
            <h3 style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #1E1E1E; margin: 0 0 14px;">Áudio e som</h3>
            <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 20px;">
              <div style="margin-bottom: 20px;">
                <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; display: flex; align-items: center; gap: 6px; margin-bottom: 10px;">
                  <Volume2 :size="14" :stroke-width="1.8" color="#656966" /> Volume geral
                </label>
                <div style="display: flex; align-items: center; gap: 12px;">
                  <Volume2 :size="13" :stroke-width="1.8" color="#BDBABA" />
                  <input type="range" min="0" max="100" defaultValue="70" class="accent-[#009957]" style="flex: 1;" />
                  <span style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #1E1E1E; width: 32px; text-align: right;">70%</span>
                </div>
              </div>
            </div>
          </div>
          <div><button class="transition-opacity hover:opacity-80" style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #ffffff; background: #009957; border: none; border-radius: 8px; padding: 10px 20px; cursor: pointer;">Guardar preferências</button></div>
        </div>
      </template>

      <!-- ── TAB: NOTIFICAÇÕES ── -->
      <template v-else-if="activeNav === 'Notificações'">
        <div style="max-width: 800px; display: flex; flex-direction: column; gap: 32px; margin-top: 6px;">
          <div>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Notificações</h2>
            <p style="font-family: Inter, sans-serif; font-size: 14px; color: #9E9E9E; margin: 0;">Gerir como e quando recebes notificações</p>
          </div>
          <section>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Tipos de notificação</h2>
            <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; overflow: hidden; padding: 8px 20px 12px;">
              <div style="display: flex; flex-direction: column; gap: 14px; padding-top: 8px;">
                <label v-for="lbl in ['Lembretes de eventos', 'Alertas de prazos de entrega', 'Notificações de progresso de estudo', 'Notificações e sugestões da IA']" :key="lbl" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                  <input type="checkbox" checked class="accent-[#009957]" style="width: 16px; height: 16px; cursor: pointer; flex-shrink: 0;" />
                  <span style="font-family: Inter, sans-serif; font-size: 14px; color: #1E1E1E;">{{ lbl }}</span>
                </label>
              </div>
            </div>
          </section>
          <section>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Notificações recentes</h2>
            <p v-if="recentNotifs.length === 0" style="font-family: Inter, sans-serif; font-size: 14px; color: #BDBABA; margin: 0;">Sem notificações recentes.</p>
            <div v-else style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; overflow: hidden;">
              <div v-for="(notif, idx) in recentNotifs" :key="notif.id">
                <div v-if="idx > 0" style="height: 1px; background: #F0F0F0;" />
                <div style="display: flex; align-items: center; padding: 16px 20px; gap: 14px;">
                  <div style="width: 36px; height: 36px; border-radius: 9px; background: #F5F5F5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <component :is="notif.type === 'alert' ? AlertCircle : Brain" :size="16" :stroke-width="1.8" :color="notif.type === 'alert' ? '#E53935' : '#009957'" />
                  </div>
                  <div style="flex: 1;"><p style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #1E1E1E; margin: 0;">{{ notif.title }}</p><p style="font-family: Inter, sans-serif; font-size: 12px; color: #BDBABA; margin: 0;">{{ notif.time }}</p></div>
                  <button @click="recentNotifs = recentNotifs.filter(n => n.id !== notif.id)" style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 8px; padding: 6px 14px; cursor: pointer; flex-shrink: 0;">Dispensar</button>
                </div>
              </div>
            </div>
          </section>
          <div><button class="transition-opacity hover:opacity-80" style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #ffffff; background: #009957; border: none; border-radius: 8px; padding: 10px 20px; cursor: pointer;">Guardar definições</button></div>
        </div>
      </template>

      <!-- ── TAB: AJUDA ── -->
      <template v-else-if="activeNav === 'Ajuda'">
        <div style="max-width: 800px; display: flex; flex-direction: column; gap: 32px; margin-top: 6px;">
          <div>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Ajuda e suporte</h2>
            <p style="font-family: Inter, sans-serif; font-size: 14px; color: #9E9E9E; margin: 0;">Obtém apoio e aprende mais sobre a plataforma</p>
          </div>
          <section>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Ajuda rápida</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
              <button v-for="{ label, icon, desc } in [{ label: 'Guia do utilizador', icon: Book, desc: 'Aprende a usar todas as funcionalidades' }, { label: 'Tutoriais em vídeo', icon: Video, desc: 'Vídeos passo a passo para cada feature' }, { label: 'Documentação', icon: FileText, desc: 'Referência técnica e guias avançados' }, { label: 'FAQ', icon: HelpCircle, desc: 'Respostas às perguntas mais frequentes' }]" :key="label" class="transition-colors hover:bg-gray-50 text-left" style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 12px; padding: 16px; cursor: pointer; display: flex; align-items: flex-start; gap: 14px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: #EDF9EF; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><component :is="icon" :size="18" :stroke-width="1.8" color="#009957" /></div>
                <div><p style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #1E1E1E; margin: 0 0 3px;">{{ label }}</p><p style="font-family: Inter, sans-serif; font-size: 12px; color: #9E9E9E; margin: 0; line-height: 1.4;">{{ desc }}</p></div>
              </button>
            </div>
          </section>
          <section>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Perguntas frequentes</h2>
            <div style="display: flex; flex-direction: column; gap: 0;">
              <div
                v-for="(faq, idx) in FAQS"
                :key="faq.id"
                :style="{ background: '#ffffff', border: '1px solid #E5E5E5', borderRadius: idx === 0 ? '12px 12px 0 0' : idx === FAQS.length - 1 ? '0 0 12px 12px' : '0', borderTop: idx > 0 ? 'none' : '1px solid #E5E5E5', padding: '16px 20px', cursor: 'pointer' }"
                @click="openFaq = openFaq === faq.id ? null : faq.id"
              >
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
                  <span style="font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #1E1E1E;">{{ faq.q }}</span>
                  <component :is="openFaq === faq.id ? ChevronUp : ChevronDown" :size="16" :stroke-width="2" :color="openFaq === faq.id ? '#009957' : '#9E9E9E'" style="flex-shrink: 0;" />
                </div>
                <p v-if="openFaq === faq.id" style="font-family: Inter, sans-serif; font-size: 14px; color: #656966; margin: 12px 0 0; line-height: 1.6;">{{ faq.a }}</p>
              </div>
            </div>
          </section>
          <section>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 16px; color: #1E1E1E; margin: 0 0 14px;">Informação do sistema</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
              <div v-for="{ label, value, accent } in [{ label: 'Versão', value: '2.4.1', accent: false }, { label: 'Última atualização', value: 'Hoje', accent: false }, { label: 'Plataforma', value: 'Web App', accent: false }, { label: 'Estado', value: 'Operacional', accent: true }]" :key="label" style="background: #F9F9F9; border-radius: 10px; padding: 16px;">
                <p style="font-family: Inter, sans-serif; font-size: 12px; color: #9E9E9E; margin: 0 0 4px;">{{ label }}</p>
                <p :style="{ fontFamily: 'Inter, sans-serif', fontWeight: 600, fontSize: '14px', color: accent ? '#009957' : '#1E1E1E', margin: 0 }">{{ value }}</p>
              </div>
            </div>
          </section>
        </div>
      </template>

      <!-- ── PLACEHOLDER ── -->
      <template v-else-if="!IMPLEMENTED.has(activeNav)">
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; flex: 1; gap: 12px; min-height: 320px;">
          <div style="width: 56px; height: 56px; border-radius: 14px; background: #F5F5F5; display: flex; align-items: center; justify-content: center;">
            <span style="font-family: Inter, sans-serif; font-weight: 700; font-size: 22px; color: #BDBABA;">{{ activeNav.charAt(0) }}</span>
          </div>
          <p style="font-family: Inter, sans-serif; font-weight: 600; font-size: 16px; color: #1E1E1E; margin: 0;">{{ activeNav }}</p>
          <p style="font-family: Inter, sans-serif; font-size: 14px; color: #BDBABA; margin: 0;">Esta secção estará disponível em breve.</p>
        </div>
      </template>
    </div>
  </div>

  <!-- Modal: Delete confirmation (student) -->
  <Teleport to="body">
    <div v-if="itemToDelete" class="fixed inset-0 z-[1000] flex items-center justify-center" style="background: rgba(0,0,0,0.4);" @click="itemToDelete = null">
      <div @click.stop style="width: 400px; background: #ffffff; border-radius: 16px; padding: 24px; box-shadow: 0 8px 40px rgba(0,0,0,0.16); display: flex; flex-direction: column; gap: 20px;">
        <div style="display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center;">
          <div style="width: 56px; height: 56px; border-radius: 14px; background: #FEF2F2; display: flex; align-items: center; justify-content: center;"><AlertTriangle :size="26" :stroke-width="1.8" color="#DC2626" /></div>
          <div>
            <h2 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 18px; color: #1E1E1E; margin: 0 0 6px;">Tens a certeza?</h2>
            <p style="font-family: Inter, sans-serif; font-size: 14px; color: #9E9E9E; margin: 0; line-height: 1.5;">Esta ação é irreversível. O item será apagado permanentemente.</p>
          </div>
        </div>
        <div style="display: flex; gap: 10px;">
          <button @click="itemToDelete = null" class="transition-colors hover:bg-gray-50" style="flex: 1; font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 10px; padding: 11px 0; cursor: pointer;">Cancelar</button>
          <button @click="confirmDelete" class="transition-opacity hover:opacity-80" style="flex: 1; font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #ffffff; background: #DC2626; border: none; border-radius: 10px; padding: 11px 0; cursor: pointer;">Eliminar</button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Modal: Rename drive item (teacher) -->
  <Teleport to="body">
    <div v-if="renameTarget" class="fixed inset-0 z-[1000] flex items-center justify-center" style="background: rgba(0,0,0,0.4);" @click="renameTarget = null">
      <div @click.stop style="width: 420px; background: #ffffff; border-radius: 16px; padding: 24px; box-shadow: 0 8px 40px rgba(0,0,0,0.16); display: flex; flex-direction: column; gap: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <h3 style="font-family: Inter, sans-serif; font-weight: 700; font-size: 17px; color: #1E1E1E; margin: 0;">{{ renameTarget.type === 'folder' ? 'Renomear pasta' : 'Renomear ficheiro' }}</h3>
          <button @click="renameTarget = null" style="background: none; border: none; cursor: pointer; padding: 4px; border-radius: 5px; display: flex; align-items: center;"><X :size="18" :stroke-width="2" color="#9E9E9E" /></button>
        </div>
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <label style="font-family: Inter, sans-serif; font-weight: 500; font-size: 13px; color: #656966;">Novo nome *</label>
          <input v-model="renameTarget.name" autofocus type="text" style="font-family: Inter, sans-serif; font-size: 14px; color: #1E1E1E; border: 1px solid #E5E5E5; border-radius: 8px; padding: 9px 12px; background: #F7F7F7; outline: none; width: 100%; box-sizing: border-box;" @keydown.enter="handleRenameConfirm" />
        </div>
        <div style="display: flex; gap: 10px;">
          <button @click="renameTarget = null" class="transition-colors hover:bg-gray-50" style="flex: 1; font-family: Inter, sans-serif; font-weight: 500; font-size: 14px; color: #656966; background: none; border: 1px solid #E5E5E5; border-radius: 10px; padding: 10px 0; cursor: pointer;">Cancelar</button>
          <button @click="handleRenameConfirm" :disabled="!renameTarget.name.trim()" class="transition-opacity hover:opacity-80 disabled:opacity-40 disabled:cursor-not-allowed" style="flex: 1; font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: #ffffff; background: #009957; border: none; border-radius: 10px; padding: 10px 0; cursor: pointer;">Guardar</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
