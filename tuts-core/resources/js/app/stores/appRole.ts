import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiFetch } from '@/app/services/api'
import type { AppRole } from '@/app/types'

export interface AuthUser {
  id: number
  name: string
  email: string
  role: 'aluno' | 'professor' | 'student' | 'teacher' | string
}

function normalizeRole(role: string | undefined | null): AppRole {
  return role === 'professor' || role === 'teacher' ? 'teacher' : 'student'
}

export const useAppRoleStore = defineStore('appRole', () => {
  const role = ref<AppRole>('student')
  const user = ref<AuthUser | null>(null)
  const loading = ref(false)
  const initialized = ref(false)

  async function loadMe() {
    loading.value = true

    try {
      const response = await apiFetch<{ user: AuthUser }>('/api/me')
      user.value = response.user
      role.value = normalizeRole(response.user.role)
      initialized.value = true
      return response.user
    } finally {
      loading.value = false
    }
  }

  function setRoleFromBackend(nextRole: string) {
    role.value = normalizeRole(nextRole)
  }

  async function logout() {
    await apiFetch('/api/logout', { method: 'POST' })
    user.value = null
    initialized.value = false
    window.location.href = '/login'
  }

  return {
    role,
    user,
    loading,
    initialized,
    loadMe,
    setRoleFromBackend,
    logout,
  }
})
