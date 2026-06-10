import cadeirasMtcRaw from '@/cadeiras_mtc.json'

export interface UCData {
  id: string
  subjectId?: number
  name: string
  teacher: string
  year: string
  academicYear: string
  cover: string
  shortCode: string
  description: string
  url?: string | null
}

type RawCadeira = Record<string, unknown>

const UC_COVERS = [
  'linear-gradient(135deg, #009957 0%, #43e97b 100%)',
  'linear-gradient(135deg, #1E1E1E 0%, #656966 100%)',
  'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
  'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
  'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
  'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
]

function slugify(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/&/g, 'e')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

function readString(obj: RawCadeira, keys: string[], fallback = ''): string {
  for (const key of keys) {
    const value = obj[key]

    if (typeof value === 'string' && value.trim()) return value.trim()
    if (typeof value === 'number') return String(value)
  }

  return fallback
}

function shortCode(name: string): string {
  const words = name.split(/\s+/).filter((word) => word.length > 2)
  const initials = words.slice(0, 4).map((word) => word[0]?.toUpperCase()).join('')
  return initials || name.slice(0, 3).toUpperCase()
}

function fallbackCover(index: number): string {
  return UC_COVERS[index % UC_COVERS.length]
}

function normalizeLocalCadeira(raw: RawCadeira, index: number): UCData {
  const name = readString(raw, ['nome_uc', 'name', 'nome', 'title'], `UC ${index + 1}`)
  const url = readString(raw, ['url_uc', 'url'], '')

  return {
    id: slugify(name),
    name,
    teacher: 'Docente a definir',
    year: 'Ano não definido',
    academicYear: '2025/2026',
    cover: fallbackCover(index),
    shortCode: shortCode(name),
    description: `Unidade curricular de ${name}.`,
    url: url || null,
  }
}

export function normalizeBackendSubject(raw: {
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
}): UCData {
  return {
    id: raw.id || slugify(raw.name),
    subjectId: raw.subject_id,
    name: raw.name,
    teacher: raw.teacher || 'Docente a definir',
    year: raw.year || 'Ano não definido',
    academicYear: raw.academicYear || '2025/2026',
    cover: raw.cover || fallbackCover(Number(raw.subject_id ?? 0)),
    shortCode: raw.shortCode || shortCode(raw.name),
    description: raw.description || `Unidade curricular de ${raw.name}.`,
    url: raw.url ?? null,
  }
}

const rawArray = Array.isArray(cadeirasMtcRaw) ? cadeirasMtcRaw as RawCadeira[] : []

export const UC_LIST: UCData[] = rawArray.map(normalizeLocalCadeira)

export const UC_MAP: Record<string, UCData> = Object.fromEntries(
  UC_LIST.map((uc) => [uc.id, uc]),
)

export function mapUCsById(ucs: UCData[]): Record<string, UCData> {
  return Object.fromEntries(ucs.map((uc) => [uc.id, uc]))
}
