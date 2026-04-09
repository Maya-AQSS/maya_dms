/**
 * Cliente HTTP mínimo para la API Laravel (prefijo /api/v1).
 * El JWT se guarda vía {@link bootstrapSessionToken} (URL corporativa o VITE_DEV_ACCESS_TOKEN).
 */
import { getStoredAccessToken } from '../lib/sessionToken';

const DEFAULT_BASE_URL = 'http://localhost:8001/api/v1';

function getBaseUrl(): string {
  const raw = import.meta.env.VITE_API_URL ?? DEFAULT_BASE_URL;
  return raw.replace(/\/$/, '');
}

export async function apiGetJson<T>(path: string): Promise<T> {
  const base = getBaseUrl();
  const normalizedPath = path.replace(/^\//, '');
  const url = path.startsWith('http') ? path : `${base}/${normalizedPath}`;

  const headers: HeadersInit = {
    Accept: 'application/json',
  };

  const token = getStoredAccessToken();
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(url, { method: 'GET', headers });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
  }

  return response.json() as Promise<T>;
}
