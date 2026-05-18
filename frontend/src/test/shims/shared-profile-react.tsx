/**
 * Shim de `@maya/shared-profile-react` para Vitest.
 *
 * Resuelve el problema "dual-react" del monorepo (el package real bundle su
 * propia copia de React, lo que rompe el context en tests). Esta versión usa
 * el mismo React que la app y expone una API mínima compatible.
 */
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';

/**
 * Forma canónica cross-app (2026-05-18). Los campos legacy `study_type_ids`,
 * `study_ids`, `module_ids`, `team_ids`, `permissions`, `department`,
 * `teams`, `source` se mantienen como opcionales solo para compatibilidad
 * con tests que aún no se han renombrado — el backend ya no los expone.
 */
export interface BaseMeProfile {
  id?: string;
  email?: string | null;
  name?: string | null;
  locale?: string;
  permisos?: string[];
  tipo_estudios?: string[];
  estudios?: string[];
  modulos?: string[];
  equipos?: unknown[];
  // Legacy (deprecado, eliminar tras rolling de los 5 backends):
  department?: string | null;
  study_type_ids?: string[];
  study_ids?: string[];
  module_ids?: string[];
  team_ids?: string[];
  permissions?: string[];
  teams?: unknown[];
  source?: 'fdw' | 'idp' | string;
}

interface UserProfileContextValue<TProfile extends BaseMeProfile> {
  profile: TProfile | null;
  loading: boolean;
  error: Error | null;
  setProfile: (next: TProfile) => void;
  load: () => Promise<void>;
  hasPermission: (code: string) => boolean;
}

const UserProfileContext = createContext<UserProfileContextValue<BaseMeProfile> | null>(null);

export interface UserProfileProviderProps<TProfile extends BaseMeProfile> {
  children: ReactNode;
  fetchProfile: () => Promise<TProfile>;
}

export function UserProfileProvider<TProfile extends BaseMeProfile>({
  children,
  fetchProfile,
}: UserProfileProviderProps<TProfile>) {
  const [profile, setProfileState] = useState<TProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);

  const setProfile = useCallback((next: TProfile) => {
    setProfileState(next);
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await fetchProfile();
      setProfileState(data);
    } catch (e) {
      setError(e instanceof Error ? e : new Error(String(e)));
    } finally {
      setLoading(false);
    }
  }, [fetchProfile]);

  useEffect(() => {
    void load();
  }, [load]);

  const hasPermission = useCallback(
    (code: string) => {
      const list = profile?.permisos ?? profile?.permissions;
      return Array.isArray(list) && list.includes(code);
    },
    [profile],
  );

  const value = useMemo<UserProfileContextValue<BaseMeProfile>>(
    () => ({
      profile: profile as BaseMeProfile | null,
      loading,
      error,
      setProfile: setProfile as (next: BaseMeProfile) => void,
      load,
      hasPermission,
    }),
    [profile, loading, error, setProfile, load, hasPermission],
  );

  return <UserProfileContext.Provider value={value}>{children}</UserProfileContext.Provider>;
}

export function useUserProfile<TProfile extends BaseMeProfile = BaseMeProfile>(): UserProfileContextValue<TProfile> {
  const ctx = useContext(UserProfileContext);
  if (!ctx) {
    throw new Error('useUserProfile debe usarse dentro de UserProfileProvider');
  }
  return ctx as UserProfileContextValue<TProfile>;
}

// ── Helpers de createProfileApi ─────────────────────────────────────────────
// Los tests rara vez tocan esta superficie; exponemos stubs para que imports
// estáticos del package no fallen.

export interface ProfileApi<TProfile extends BaseMeProfile> {
  fetchMe: () => Promise<TProfile>;
  updateLocale: (locale: string) => Promise<void>;
  updateEmployee: (employeeId: string) => Promise<void>;
}

export function createProfileApi<TProfile extends BaseMeProfile>(
  _apiClient: unknown,
): ProfileApi<TProfile> {
  void _apiClient;
  return {
    fetchMe: async () => {
      throw new Error('@maya/shared-profile-react (shim): fetchMe sin mock');
    },
    updateLocale: async () => undefined,
    updateEmployee: async () => undefined,
  };
}
