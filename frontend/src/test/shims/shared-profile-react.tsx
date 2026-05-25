/**
 * Shim de `@ceedcv-maya/shared-profile-react` para Vitest.
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

export type SharedUserTeam = {
  id: string;
  name: string;
  description?: string | null;
  role?: string | null;
  is_department?: boolean;
};

// Alias para compatibilidad con import { UserTeam } from '@ceedcv-maya/shared-profile-react'.
export type UserTeam = SharedUserTeam;

/**
 * Forma canónica cross-app (snake_case en inglés). El campo legacy
 * `department` y `source` se mantienen opcionales solo para tests/fixtures
 * que aún los referencian — el backend ya no los expone.
 */
export interface BaseMeProfile {
  id?: string;
  email?: string | null;
  name?: string | null;
  locale?: string;
  permissions?: string[];
  study_type_ids?: string[];
  study_ids?: string[];
  module_ids?: string[];
  team_ids?: string[];
  teams?: UserTeam[];
  // Legacy (deprecado, eliminar tras refactor de tests):
  department?: string | null;
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
      const list = profile?.permissions;
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
      throw new Error('@ceedcv-maya/shared-profile-react (shim): fetchMe sin mock');
    },
    updateLocale: async () => undefined,
    updateEmployee: async () => undefined,
  };
}
