<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import type { Component } from 'vue'
import {
  Home, Calendar, FolderOpen, MessageSquare,
  User, LogOut, BookOpen, PieChart,
} from '@lucide/vue'
import { useAppRoleStore } from '@/app/stores/appRole'
import type { ActivePageId } from '@/app/types'

defineOptions({ name: 'AppSidebar' })

const props = withDefaults(defineProps<{
  activePage?: ActivePageId
}>(), {
  activePage: 'home',
})

const router = useRouter()
const roleStore = useAppRoleStore()

interface SubItem {
  label: string
  path: string
}

interface NavItem {
  id: ActivePageId | string
  icon: Component
  label: string
  path: string
  subItems?: SubItem[]
}

const STUDENT_NAV: NavItem[] = [
  { id: 'home', icon: Home, label: 'Home', path: '/home' },
  {
    id: 'calendar',
    icon: Calendar,
    label: 'Calendário',
    path: '/calendar',
    subItems: [
      { label: 'Planificação', path: '/planificacao' },
      { label: 'Os meus planos', path: '/meus-planos' },
    ],
  },
  {
    id: 'ucs',
    icon: FolderOpen,
    label: "UC's & Espaços",
    path: '/ucs',
    subItems: [
      { label: 'Conversas', path: '/ucs?tab=conversas' },
      { label: 'Os meus espaços', path: '/ucs?tab=espacos' },
    ],
  },
  { id: 'chat', icon: MessageSquare, label: 'Chat', path: '/chat' },
]

const TEACHER_NAV: NavItem[] = [
  { id: 'gestao', icon: BookOpen, label: "Gestão de UC's", path: '/ucs' },
  { id: 'dashboard', icon: PieChart, label: 'Dashboard Pedagógico', path: '/dashboard' },
  { id: 'calendar', icon: Calendar, label: 'Calendário', path: '/calendar' },
  { id: 'profile', icon: User, label: 'Perfil', path: '/profile' },
]

const currentNav = computed<NavItem[]>(() =>
  roleStore.role === 'student' ? STUDENT_NAV : TEACHER_NAV,
)

function navigate(path: string): void {
  router.push(path)
}

function logout(): void {
  roleStore.logout().catch((error) => {
    console.error('[TUTS] Falha no logout.', error)
    window.location.href = '/login'
  })
}
</script>

<template>
  <aside
    :class="[
      'fixed left-0 top-0 h-full z-[100]',
      'flex flex-col py-6',
      'w-[80px] hover:w-[240px] transition-all duration-300 ease-in-out',
      'overflow-hidden',
      'group',
    ]"
    style="background: #1E1E1E;"
  >
    <div class="mb-8 w-full flex flex-row items-center justify-start flex-shrink-0">
      <div class="w-[80px] flex-shrink-0 flex items-center justify-center">
        <div
          class="flex items-center justify-center rounded-xl"
          style="width: 40px; height: 40px; background: #009957;"
        >
          <span style="font-family: Inter, sans-serif; font-weight: 700; font-size: 14px; color: #ffffff; letter-spacing: 0.05em;">
            T
          </span>
        </div>
      </div>

      <div
        class="flex flex-col flex-1 opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap"
        style="gap: 2px;"
      >
        <span style="font-family: Inter, sans-serif; font-weight: 800; font-size: 16px; color: #ffffff; letter-spacing: 0.08em;">
          TUT'S
        </span>
        <span
          :style="{
            fontFamily: 'Inter, sans-serif',
            fontWeight: 600,
            fontSize: '9px',
            letterSpacing:'0.06em',
            color: roleStore.role === 'teacher' ? '#93C5FD' : '#6EE7B7',
            background: roleStore.role === 'teacher' ? 'rgba(147,197,253,0.15)' : 'rgba(110,231,183,0.15)',
            borderRadius: '3px',
            padding: '2px 5px',
            display: 'inline-block',
            width: 'fit-content',
          }"
        >
          {{ roleStore.role === 'teacher' ? 'DOCENTE' : 'ESTUDANTE' }}
        </span>
      </div>
    </div>

    <nav class="flex flex-col items-stretch justify-start flex-1 w-full gap-2">
      <div
        v-for="item in currentNav"
        :key="item.id"
        class="w-full flex flex-col"
      >
        <button
          :class="[
            'w-full flex flex-row items-center justify-start rounded-xl',
            'transition-colors duration-150',
            activePage !== item.id ? 'hover:bg-white/[0.07]' : '',
          ]"
          :style="{
            background: activePage === item.id ? 'rgba(0,153,87,0.15)' : 'transparent',
            border: 'none',
            cursor: 'pointer',
            outline: 'none',
            padding: '0',
          }"
          @click="navigate(item.path)"
        >
          <div
            class="w-[80px] flex-shrink-0 flex items-center justify-center"
            style="height: 48px;"
          >
            <component
              :is="item.icon"
              :size="22"
              :stroke-width="activePage === item.id ? 2.2 : 1.8"
              :color="activePage === item.id ? '#009957' : 'rgba(255,255,255,0.55)'"
            />
          </div>

          <span
            class="flex-1 text-left opacity-0 group-hover:opacity-100 whitespace-nowrap transition-opacity duration-300"
            :style="{
              fontFamily: 'Inter, sans-serif',
              fontWeight: 600,
              fontSize: '14px',
              color: activePage === item.id ? '#009957' : '#ffffff',
            }"
          >
            {{ item.label }}
          </span>
        </button>

        <div
          v-if="item.subItems && item.subItems.length > 0"
          :class="[
            'w-full flex flex-col overflow-hidden',
            'max-h-0 opacity-0',
            'group-hover:max-h-[200px] group-hover:opacity-100',
            'transition-all duration-300 ease-in-out',
          ]"
        >
          <button
            v-for="sub in item.subItems"
            :key="sub.label"
            class="w-full text-left text-[#BDBABA] hover:text-white transition-colors duration-150 whitespace-nowrap"
            :style="{
              paddingLeft: '80px',
              paddingTop: '8px',
              paddingBottom: '8px',
              fontFamily: 'Inter, sans-serif',
              fontWeight: 400,
              fontSize: '13px',
              background: 'none',
              border: 'none',
              cursor: 'pointer',
              outline: 'none',
            }"
            @click.stop="navigate(sub.path)"
          >
            {{ sub.label }}
          </button>
        </div>
      </div>
    </nav>

    <div class="flex flex-col w-full flex-shrink-0 gap-1">
      <button
        v-if="roleStore.role === 'student'"
        :class="[
          'w-full flex flex-row items-center justify-start rounded-xl',
          'transition-colors duration-150',
          activePage !== 'profile' ? 'hover:bg-white/[0.07]' : '',
        ]"
        :style="{
          background: activePage === 'profile' ? 'rgba(0,153,87,0.15)' : 'transparent',
          border: 'none',
          cursor: 'pointer',
          outline: 'none',
          padding: '0',
        }"
        @click="navigate('/profile')"
      >
        <div class="w-[80px] flex-shrink-0 flex items-center justify-center" style="height: 48px;">
          <User
            :size="22"
            :stroke-width="1.8"
            :color="activePage === 'profile' ? '#009957' : 'rgba(255,255,255,0.55)'"
          />
        </div>
        <span
          class="flex-1 text-left opacity-0 group-hover:opacity-100 whitespace-nowrap transition-opacity duration-300"
          :style="{
            fontFamily: 'Inter, sans-serif',
            fontWeight: 600,
            fontSize: '14px',
            color: activePage === 'profile' ? '#009957' : '#ffffff',
          }"
        >
          Perfil
        </span>
      </button>

      <button
        class="w-full flex flex-row items-center justify-start rounded-xl hover:bg-white/[0.07] transition-colors duration-150"
        style="background: transparent; border: none; cursor: pointer; outline: none; padding: 0;"
        @click="logout"
      >
        <div class="w-[80px] flex-shrink-0 flex items-center justify-center" style="height: 48px;">
          <LogOut :size="20" :stroke-width="1.8" color="rgba(255,255,255,0.35)" />
        </div>
        <span
          class="flex-1 text-left opacity-0 group-hover:opacity-100 whitespace-nowrap transition-opacity duration-300"
          style="font-family: Inter, sans-serif; font-weight: 600; font-size: 14px; color: rgba(255,255,255,0.35);"
        >
          Sair
        </span>
      </button>
    </div>
  </aside>
</template>
