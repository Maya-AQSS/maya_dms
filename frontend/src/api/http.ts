/**
 * Cliente HTTP autenticado — factory inyectado localmente para evitar problemas de resolución en Vitest.
 */
import Keycloak from 'keycloak-js'
import { oidcAuthService } from '../auth/oidcAdapter'

export class ApiHttpError extends Error {
  readonly status: number

  constructor(message: string, status: number) {
    super(message)
    this.name = 'ApiHttpError'
    this.status = status
  }
}

export type ApiFetchOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
}

export interface ApiClient {
  apiFetchJson: <T>(path: string, options?: ApiFetchOptions) => Promise<T>
  apiGetJson: <T>(path: string) => Promise<T>
  buildApiUrl: (path: string) => string
  getBearerToken: () => Promise<string | null>
}

function createApiClient(keycloak: Keycloak, baseUrl: string): ApiClient {
  function normalizeBase(url: string): string {
    return (url ?? '').replace(/\/$/, '')
  }

  function buildApiUrl(path: string): string {
    if (path.startsWith('http')) return path
    const base = normalizeBase(baseUrl)
    return `${base}/${path.replace(/^\//, '')}`
  }

  async function appendBearer(headers: Record<string, string>): Promise<void> {
    if (!keycloak.authenticated) return
    await keycloak.updateToken(30).catch(() => keycloak.login())
    if (keycloak.token) {
      headers.Authorization = `Bearer ${keycloak.token}`
    }
  }

  async function authHeaders(jsonBody: boolean): Promise<HeadersInit> {
    const headers: Record<string, string> = { Accept: 'application/json' }
    if (jsonBody) headers['Content-Type'] = 'application/json'
    await appendBearer(headers)
    return headers
  }

  async function parseErrorMessage(response: Response): Promise<string> {
    const ct = response.headers.get('content-type') ?? ''
    if (ct.includes('application/json')) {
      try {
        const body = (await response.json()) as { message?: string; error?: string }
        return body.message ?? body.error ?? response.statusText
      } catch {
        return response.statusText
      }
    }
    return response.statusText
  }

  async function apiFetchJson<T>(path: string, options: ApiFetchOptions = {}): Promise<T> {
    const method = options.method ?? 'GET'
    const hasBody = options.body !== undefined && method !== 'GET'
    const url = buildApiUrl(path)

    const response = await fetch(url, {
      method,
      headers: await authHeaders(hasBody),
      body: hasBody ? JSON.stringify(options.body) : undefined,
    })

    if (!response.ok) {
      if (response.status === 401) keycloak.login()
      const msg = await parseErrorMessage(response)
      throw new ApiHttpError(msg || `HTTP ${response.status}`, response.status)
    }

    if (response.status === 204) return undefined as T

    const text = await response.text()
    if (text === '') return undefined as T

    return JSON.parse(text) as T
  }

  async function apiGetJson<T>(path: string): Promise<T> {
    return apiFetchJson<T>(path, { method: 'GET' })
  }

  async function getBearerToken(): Promise<string | null> {
    const headers: Record<string, string> = {}
    await appendBearer(headers)
    if (!headers.Authorization) return null
    return headers.Authorization.replace(/^Bearer\s+/i, '')
  }

  return { apiFetchJson, apiGetJson, buildApiUrl, getBearerToken }
}

const DEFAULT_BASE_URL = 'http://maya-dms-api.localhost/api/v1'
const baseUrl = (import.meta.env.VITE_API_URL as string | undefined) ?? DEFAULT_BASE_URL

const client = createApiClient(oidcAuthService.keycloak, baseUrl)

export const { apiFetchJson, apiGetJson, buildApiUrl } = client
