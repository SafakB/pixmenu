import type { Site } from './types'

export class NotFoundError extends Error {
  constructor() {
    super('not_found')
    this.name = 'NotFoundError'
  }
}

export interface SiteQuery {
  restaurant?: string
  host?: string
  branch?: string
  lang?: string
}

/** API kökü: env değeri (sondaki / atılır) veya aynı origin'deki /api/v1. */
export function apiBase(envUrl: string | undefined): string {
  const base = (envUrl ?? '').trim()

  return (base === '' ? '/api/v1' : base).replace(/\/+$/, '')
}

export function siteUrl(base: string, query: SiteQuery): string {
  const params = new URLSearchParams()

  for (const key of ['restaurant', 'host', 'branch', 'lang'] as const) {
    const value = query[key]

    if (value) {
      params.set(key, value)
    }
  }

  return `${base}/site?${params.toString()}`
}

/**
 * Tek istekte restoran + şubeler + (seçiliyse) menü.
 *
 * "Basit" CORS isteğidir: özel başlık yok, çerez gönderilmez → preflight oluşmaz.
 */
export async function fetchSite(query: SiteQuery, signal?: AbortSignal): Promise<Site> {
  const response = await fetch(siteUrl(apiBase(import.meta.env.VITE_API_URL), query), {
    signal,
    credentials: 'omit',
    headers: { Accept: 'application/json' },
  })

  if (response.status === 404) {
    throw new NotFoundError()
  }

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`)
  }

  const body = (await response.json()) as { status?: string; data?: Site }

  if (body.status !== 'success' || body.data === undefined) {
    throw new Error('Beklenmeyen API yanıtı')
  }

  return body.data
}
