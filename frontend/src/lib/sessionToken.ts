/**
 * Sesión API: JWT corporativo.
 * No hay login en Maya; el token llega desde el dashboard corporativo (query/hash en el primer load)
 * o, en desarrollo, desde VITE_DEV_ACCESS_TOKEN.
 */
export const ACCESS_TOKEN_STORAGE_KEY = 'access_token';

export function getStoredAccessToken(): string | null {
  return localStorage.getItem(ACCESS_TOKEN_STORAGE_KEY);
}

export function setStoredAccessToken(token: string): void {
  localStorage.setItem(ACCESS_TOKEN_STORAGE_KEY, token);
}

export function clearStoredAccessToken(): void {
  localStorage.removeItem(ACCESS_TOKEN_STORAGE_KEY);
}

const QUERY_AND_HASH_KEYS = ['access_token', 'token', 'jwt'] as const;

function stripTokenFromSearchParams(params: URLSearchParams): void {
  for (const key of QUERY_AND_HASH_KEYS) {
    params.delete(key);
  }
}

/**
 * Si el dashboard redirige con ?access_token= / ?token= / ?jwt= (o equivalente en #hash),
 * persiste el valor y limpia la URL para no dejar el token en la barra de direcciones.
 */
export function bootstrapSessionToken(): void {
  const params = new URLSearchParams(window.location.search);
  for (const key of QUERY_AND_HASH_KEYS) {
    const value = params.get(key);
    if (value) {
      setStoredAccessToken(value);
      stripTokenFromSearchParams(params);
      const search = params.toString();
      const url = `${window.location.pathname}${search ? `?${search}` : ''}${window.location.hash}`;
      window.history.replaceState({}, '', url);
      return;
    }
  }

  const rawHash = window.location.hash;
  if (rawHash.length > 1) {
    const hashBody = rawHash.startsWith('#') ? rawHash.slice(1) : rawHash;
    const hashParams = new URLSearchParams(hashBody);
    for (const key of QUERY_AND_HASH_KEYS) {
      const value = hashParams.get(key);
      if (value) {
        setStoredAccessToken(value);
        stripTokenFromSearchParams(hashParams);
        const rest = hashParams.toString();
        const url = `${window.location.pathname}${window.location.search}${rest ? `#${rest}` : ''}`;
        window.history.replaceState({}, '', url);
        return;
      }
    }
  }

  if (import.meta.env.DEV && import.meta.env.VITE_DEV_ACCESS_TOKEN) {
    if (!getStoredAccessToken()) {
      setStoredAccessToken(import.meta.env.VITE_DEV_ACCESS_TOKEN);
    }
  }
}
