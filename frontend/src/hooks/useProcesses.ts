import { createDataHook } from '@maya/shared-auth-react';
import { fetchProcesses } from '../api/processes';
import type { Process } from '../types/processes';

export const useProcessesQuery = createDataHook<void, { data: Process[] }>({
  queryKey: () => ['processes'],
  fetcher: () => fetchProcesses(),
  defaultOptions: { staleTime: 60_000 },
});
