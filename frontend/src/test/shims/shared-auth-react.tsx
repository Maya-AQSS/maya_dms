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
    apiFetchJson: async <_T,>(_path: string) => {
      throw new Error(
        '@ceedcv-maya/shared-auth-react (shim): apiFetchJson invoked without mock — vi.mock the calling module',
      );
    },
    apiGetJson: async <_T,>(_path: string) => {
      throw new Error(
        '@ceedcv-maya/shared-auth-react (shim): apiGetJson invoked without mock — vi.mock the calling module',
      );
    },
    buildApiUrl: (path: string) => path,
    getBearerToken: async () => 'vitest-token',
  };
}

// ── Utilidades canónicas 0.16 ───────────────────────────────────────────────
// Misma superficie que el paquete real: factories de cliente/OIDC, peer
// origins, query strings y mapeo de errores a keys i18n.

export function createServiceApiClient(
  _serviceSlug: string,
  keycloak: unknown,
  _envOverride?: string,
): ApiClient {
  void _serviceSlug;
  void _envOverride;
  return createApiClient(keycloak, 'http://vitest.local/api/v1');
}

export function createOidcAdapter(_config: unknown) {
  void _config;
  const oidcAuthService = new AuthService({});
  return {
    oidcAuthService,
    appendBearerAuthorization: async (_headers: Record<string, string>) => {
      void _headers;
    },
    triggerSignIn: () => undefined,
  };
}

export function peerOrigin(targetService: string): string {
  return `https://${targetService}.vitest.local`;
}

export function resolveServiceUrl(envValue: string | undefined, targetService: string): string {
  const trimmed = envValue?.trim();
  if (trimmed) return trimmed.replace(/\/$/, '');
  return peerOrigin(targetService);
}

/** Copia fiel del buildQueryString real (omite null/undefined/''/false/0; true→'1'; arrays→join ','). */
export function buildQueryString(params: Record<string, unknown>): string {
  const q = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === null || value === undefined || value === '' || value === false || value === 0) {
      continue;
    }
    if (Array.isArray(value)) {
      if (value.length === 0) continue;
      q.set(key, value.join(','));
      continue;
    }
    if (value === true) {
      q.set(key, '1');
      continue;
    }
    q.set(key, String(value));
  }
  const s = q.toString();
  return s ? `?${s}` : '';
}

/** Copia fiel del mapApiErrorToI18nKey real (ApiHttpError → key por status). */
export function mapApiErrorToI18nKey(
  err: unknown,
  prefix: string,
  fallbackKey = 'errorLoad',
): string {
  if (err instanceof ApiHttpError) {
    if (err.status === 401) return `${prefix}.errorUnauthorized`;
    if (err.status === 403) return `${prefix}.errorForbidden`;
    if (err.status === 404) return `${prefix}.errorNotFound`;
    if (err.status === 422) return `${prefix}.errorValidation`;
    if (err.status >= 500) return `${prefix}.errorServer`;
  }
  if (err instanceof TypeError) return `${prefix}.errorNetwork`;
  return `${prefix}.${fallbackKey}`;
}

export function mapApiError(err: unknown, prefix: string, fallbackSuffix = 'errorLoad'): Error {
  return new Error(mapApiErrorToI18nKey(err, prefix, fallbackSuffix));
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

/* ─── data hooks (TanStack-Query wrappers) ─────────────────────────────────
 *
 * Stubs ligeros para `createDataHook` / `createMutationHook` /
 * `createPaginatedDataHook`. NO usan TanStack ni QueryClient — el shim ejecuta
 * el fetcher con `useState`/`useEffect` y expone los campos mínimos que los
 * consumers usan (`data`, `isLoading`, `error`, `mutateAsync`).
 *
 * Razón: el package real bundle su propio React + @tanstack/react-query, lo que
 * en monorepo provoca dos copias de React y el clásico "Invalid hook call".
 * Para tests determinísticos preferimos un fake mínimo y verificar el contrato
 * del consumer, no la integración con TanStack.
 */
import { useCallback, useEffect, useRef, useState } from 'react';

interface FakeQueryState<TData> {
  data: TData | undefined;
  isLoading: boolean;
  error: Error | null;
}

export function createDataHook<TArgs, TData>(config: {
  queryKey: (args: TArgs) => readonly unknown[];
  fetcher: (args: TArgs) => Promise<TData>;
  defaultOptions?: unknown;
}) {
  return function useFakeQuery(args: TArgs, options: { enabled?: boolean } = {}) {
    const enabled = options.enabled !== false;
    const [state, setState] = useState<FakeQueryState<TData>>(() => ({
      data: undefined,
      isLoading: enabled,
      error: null,
    }));
    const ranRef = useRef(false);

    useEffect(() => {
      if (!enabled || ranRef.current) return;
      ranRef.current = true;
      config
        .fetcher(args)
        .then((data) => setState({ data, isLoading: false, error: null }))
        .catch((err) => setState({ data: undefined, isLoading: false, error: err as Error }));
    }, [enabled]);

    return state;
  };
}

export function createMutationHook<TVars, TData>(config: {
  mutationFn: (vars: TVars) => Promise<TData>;
  invalidates?: unknown;
  onSuccess?: unknown;
}) {
  return function useFakeMutation() {
    const [error, setError] = useState<Error | null>(null);
    const mutateAsync = useCallback(async (vars: TVars) => {
      try {
        const result = await config.mutationFn(vars);
        setError(null);
        return result;
      } catch (err) {
        setError(err as Error);
        throw err;
      }
    }, []);
    return { mutateAsync, error };
  };
}

export function createPaginatedDataHook<TArgs, TItem>(config: {
  queryKey: (args: TArgs) => readonly unknown[];
  fetcher: (args: TArgs) => Promise<{
    data: TItem[];
    current_page?: number;
    last_page?: number;
    total?: number;
    per_page?: number;
  }>;
  defaultOptions?: unknown;
}) {
  return function useFakePaginatedQuery(args: TArgs, options: { enabled?: boolean } = {}) {
    const enabled = options.enabled !== false;
    const [state, setState] = useState<{
      data: TItem[];
      currentPage: number;
      lastPage: number;
      total: number;
      perPage: number;
      isLoading: boolean;
      error: Error | null;
    }>(() => ({
      data: [],
      currentPage: 1,
      lastPage: 1,
      total: 0,
      perPage: 25,
      isLoading: enabled,
      error: null,
    }));
    const ranRef = useRef(false);

    useEffect(() => {
      if (!enabled || ranRef.current) return;
      ranRef.current = true;
      config
        .fetcher(args)
        .then((payload) =>
          setState({
            data: payload.data,
            currentPage: payload.current_page ?? 1,
            lastPage: payload.last_page ?? 1,
            total: payload.total ?? payload.data.length,
            perPage: payload.per_page ?? 25,
            isLoading: false,
            error: null,
          }),
        )
        .catch((err) =>
          setState((prev) => ({ ...prev, isLoading: false, error: err as Error })),
        );
    }, [enabled]);

    return state;
  };
}
