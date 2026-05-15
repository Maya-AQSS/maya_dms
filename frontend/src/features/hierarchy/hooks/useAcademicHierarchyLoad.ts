import { createDataHook } from '@maya/shared-auth-react';
import { fetchAcademicHierarchy } from '../../../api/academicHierarchy';
import type { AcademicHierarchy } from '../../../types/hierarchy';

/**
 * Carga la jerarquía académica una vez por sesión. Reemplaza el patrón
 * `useEffect + useState + fetch` con módulo-level cache por el helper
 * `createDataHook` (TanStack Query): la caché es por queryKey y persiste
 * mientras el QueryClient esté vivo, evitando la doble request al montar
 * varios componentes simultáneamente.
 */
const useAcademicHierarchyQuery = createDataHook<void, AcademicHierarchy>({
  queryKey: () => ['academic-hierarchy'],
  fetcher: () => fetchAcademicHierarchy(),
  defaultOptions: {
    // La jerarquía cambia muy poco — vale la pena cachearla agresivamente.
    staleTime: 5 * 60_000,
    gcTime: 10 * 60_000,
  },
});

export function useAcademicHierarchyLoad(): {
  hierarchy: AcademicHierarchy;
  loading: boolean;
  error: Error | null;
} {
  const query = useAcademicHierarchyQuery();

  return {
    hierarchy: query.data ?? [],
    loading: query.isLoading,
    error: query.error ?? null,
  };
}
