import { apiFetch } from '@/app/services/api'
import { UC_LIST, normalizeBackendSubject, type UCData } from '@/app/data/ucData'

export interface BackendSubject {
  id: string
  subject_id?: number
  name: string
  url?: string | null
  teacher?: string
  year?: string
  academicYear?: string
  cover?: string
  shortCode?: string
  description?: string
}

interface SubjectsResponse {
  status: string
  subjects: BackendSubject[]
  message?: string
}

export async function fetchMySubjects(): Promise<UCData[]> {
  try {
    const response = await apiFetch<SubjectsResponse>('/api/subjects')
    return (response.subjects ?? []).map(normalizeBackendSubject)
  } catch (error) {
    console.warn('[TUTS] Falha ao carregar UCs reais. A usar fallback local.', error)
    return UC_LIST
  }
}
