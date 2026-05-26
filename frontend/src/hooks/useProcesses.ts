import { createDataHook } from '@ceedcv-maya/shared-auth-react';
import { fetchProcesses } from '../api/processes';
import { useUserProfile } from '../features/user-profile';
import { DMS_PERMISSIONS } from '../permissions';
import type { Process } from '../types/processes';

const useProcessesQueryBase = createDataHook<void, { data: Process[] }>({
  queryKey: () => ['processes'],
  fetcher: () => fetchProcesses(),
  defaultOptions: { staleTime: 60_000 },
});

/** Catálogo de procesos; solo consulta la API si el usuario tiene `process.index`. */
export function useProcessesQuery(
  _void?: void,
  options?: { enabled?: boolean },
) {
  const { hasPermission } = useUserProfile();
  const canIndex = hasPermission(DMS_PERMISSIONS.processIndex);

  return useProcessesQueryBase(undefined, {
    ...options,
    enabled: canIndex && (options?.enabled ?? true),
  });
}
