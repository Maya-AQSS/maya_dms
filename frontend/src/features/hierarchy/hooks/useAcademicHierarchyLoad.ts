import { createDataHook } from '@ceedcv-maya/shared-auth-react';
import { fetchAcademicHierarchy, type AcademicContextLoad } from '../../../api/academicHierarchy';
import type { AcademicHierarchy } from '../../../types/hierarchy';
import type { UserTeam } from '../../../api/users';

/**
 * Carga el contexto académico del usuario (jerarquía + equipos) una vez por
 * sesión. Reemplaza el patrón `useEffect + useState + fetch` con módulo-level
 * cache por el helper `createDataHook` (TanStack Query): la caché es por
 * queryKey y persiste mientras el QueryClient esté vivo, evitando la doble
 * request al montar varios componentes simultáneamente.
 *
 * El endpoint subyacente es `GET /api/v1/me/academic-context` del paquete
 * compartido `maya-shared-profile-laravel` (filtrado server-side por user_id).
 */
const useAcademicHierarchyQuery = createDataHook<void, AcademicContextLoad>({
  queryKey: () => ['academic-context'],
  fetcher: () => fetchAcademicHierarchy(),
  defaultOptions: {
    // El contexto académico cambia muy poco — cacheado agresivamente.
    staleTime: 5 * 60_000,
    gcTime: 10 * 60_000,
  },
});

export function useAcademicHierarchyLoad(): {
  hierarchy: AcademicHierarchy;
  teams: UserTeam[];
  loading: boolean;
  error: Error | null;
} {
  const query = useAcademicHierarchyQuery();

  return {
    hierarchy: query.data?.hierarchy ?? [],
    teams: query.data?.teams ?? [],
    loading: query.isLoading,
    error: query.error ?? null,
  };
}
