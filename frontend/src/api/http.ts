/**
 * Cliente HTTP mínimo para la API Laravel (prefijo /api/v1).
 * El JWT se guarda vía {@link bootstrapSessionToken} (URL corporativa o VITE_DEV_ACCESS_TOKEN).
 */
import { getStoredAccessToken } from '../lib/sessionToken';

const DEFAULT_BASE_URL = 'http://localhost:8001/api/v1';

export class ApiHttpError extends Error {
  readonly status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = 'ApiHttpError';
    this.status = status;
  }
}

function getBaseUrl(): string {
  const raw = import.meta.env.VITE_API_URL ?? DEFAULT_BASE_URL;
  return raw.replace(/\/$/, '');
}

function buildUrl(path: string): string {
  const base = getBaseUrl();
  const normalizedPath = path.replace(/^\//, '');
  return path.startsWith('http') ? path : `${base}/${normalizedPath}`;
}

function authHeaders(jsonBody: boolean): HeadersInit {
  const headers: HeadersInit = {
    Accept: 'application/json',
  };
  if (jsonBody) {
    headers['Content-Type'] = 'application/json';
  }
  const token = getStoredAccessToken();
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  return headers;
}

async function parseErrorMessage(response: Response): Promise<string> {
  const ct = response.headers.get('content-type') ?? '';
  if (ct.includes('application/json')) {
    try {
      const body = (await response.json()) as { message?: string; error?: string };
      return body.message ?? body.error ?? response.statusText;
    } catch {
      return response.statusText;
    }
  }
  return response.statusText;
}

export type ApiFetchOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
};

export async function apiFetchJson<T>(path: string, options: ApiFetchOptions = {}): Promise<T> {
  const method = options.method ?? 'GET';
  const hasBody = options.body !== undefined && method !== 'GET';
  const url = buildUrl(path);

  const response = await fetch(url, {
    method,
    headers: authHeaders(hasBody),
    body: hasBody ? JSON.stringify(options.body) : undefined,
  });

  if (!response.ok) {
    const msg = await parseErrorMessage(response);
    throw new ApiHttpError(msg || `HTTP ${response.status}`, response.status);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  const text = await response.text();
  if (text === '') {
    return undefined as T;
  }

  return JSON.parse(text) as T;
}

export async function apiGetJson<T>(path: string): Promise<T> {
  return apiFetchJson<T>(path, { method: 'GET' });
}
