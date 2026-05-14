import type { ReactNode } from 'react';

export class AuthService {
  keycloak = {
    authenticated: true,
    token: 'vitest-token',
    updateToken: async () => true,
    login: () => undefined,
  };

  constructor(_opts: unknown) {
    void _opts;
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  return children;
}

export function useAuth() {
  return {
    isLoading: false,
    isAuthenticated: true,
    login: () => undefined,
    logout: () => undefined,
    user: null as { sub: string; email: string; name: string; preferred_username: string } | null,
  };
}

/** Misma forma que en maya-shared-auth-react para que UserProfileProvider funcione en Vitest. */
export function useOidcSession() {
  const { isLoading, isAuthenticated, login, user, logout } = useAuth();
  return {
    isOidcLoading: isLoading,
    isOidcSignedIn: isAuthenticated,
    beginSignIn: login,
    user,
    logout,
  };
}

// ── Cliente HTTP ────────────────────────────────────────────────────────────
// Los tests no llegan a hacer fetch real (las funciones de api/* se mockean
// con vi.mock). El shim ofrece la superficie mínima para que el import
// estático de `api/http.ts` no falle al cargarse.

export class ApiHttpError extends Error {
  readonly status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = 'ApiHttpError';
    this.status = status;
  }
}

export type ApiFetchOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
};

export interface ApiClient {
  apiFetchJson: <T>(path: string, options?: ApiFetchOptions) => Promise<T>;
  apiGetJson: <T>(path: string) => Promise<T>;
  buildApiUrl: (path: string) => string;
  getBearerToken: () => Promise<string | null>;
}

export function createApiClient(_keycloak: unknown, _baseUrl: string): ApiClient {
  void _keycloak;
  void _baseUrl;
  return {
    apiFetchJson: async <T,>(_path: string) => {
      throw new Error(
        '@maya/shared-auth-react (shim): apiFetchJson invoked without mock — vi.mock the calling module',
      );
    },
    apiGetJson: async <T,>(_path: string) => {
      throw new Error(
        '@maya/shared-auth-react (shim): apiGetJson invoked without mock — vi.mock the calling module',
      );
    },
    buildApiUrl: (path: string) => path,
    getBearerToken: async () => 'vitest-token',
  };
}

// ── Caché del perfil en localStorage ───────────────────────────────────────

export const USER_PROFILE_STORAGE_KEY = 'maya_user_profile';
export const LOCALE_STORAGE_KEY = 'locale';

export type CachedUserProfile = Record<string, unknown>;

export function readCachedUserProfile(): CachedUserProfile | null {
  return null;
}

export function writeCachedUserProfile(_profile: CachedUserProfile): void {
  /* no-op en tests */
}

export function updateCachedUserProfileLocale(_locale: string): void {
  /* no-op en tests */
}

export function clearCachedUserProfile(): void {
  /* no-op en tests */
}

// ── Session overrides (cross-app cookie) ────────────────────────────────────
// El shim devuelve una función de detach inerte para que el efecto que la
// instala en `UserProfileContext` pueda invocarla sin tocar cookies.

export function installOverridesListeners(): () => void {
  return () => {
    /* no-op en tests */
  };
}

export function readOverrides(): null {
  return null;
}

export function writeOverrides(_patch: Record<string, unknown>): void {
  /* no-op en tests */
}

export function clearOverrides(): void {
  /* no-op en tests */
}
