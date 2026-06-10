function csrfFromCookie(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : null
}

function csrfFromMeta(): string | null {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? null
}

export class ApiError extends Error {
  status: number
  body: string

  constructor(status: number, body: string) {
    super(body || `Pedido falhou com estado ${status}`)
    this.name = 'ApiError'
    this.status = status
    this.body = body
  }
}

export async function apiFetch<T>(
  url: string,
  options: RequestInit & { json?: unknown } = {},
): Promise<T> {
  const headers = new Headers(options.headers)

  if (!headers.has('Accept')) {
    headers.set('Accept', 'application/json')
  }

  headers.set('X-Requested-With', 'XMLHttpRequest')

  const csrf = csrfFromMeta() ?? csrfFromCookie()
  if (csrf) {
    headers.set('X-CSRF-TOKEN', csrf)
  }

  let body = options.body

  if (options.json !== undefined) {
    headers.set('Content-Type', 'application/json')
    body = JSON.stringify(options.json)
  }

  const response = await fetch(url, {
    ...options,
    headers,
    body,
    credentials: 'same-origin',
  })

  const text = await response.text()

  if (!response.ok) {
    let message = text

    try {
      const parsed = JSON.parse(text)
      message = parsed.message ?? JSON.stringify(parsed)
    } catch {
      // Mantém texto original.
    }

    throw new ApiError(response.status, message)
  }

  if (!text.trim()) {
    return undefined as T
  }

  return JSON.parse(text) as T
}
