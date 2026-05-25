import { createDataHook } from '@ceedcv-maya/shared-auth-react';
import { fetchDocuments } from '../../../api/documents';
import { useUserProfile } from '../../../features/user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import type { Document } from '../../../types/documents';

const useDocumentsQuery = createDataHook<string | undefined, Document[]>({
  queryKey: (processId) => ['documents', { processId: processId ?? null }],
  fetcher: (processId) => fetchDocuments(processId ? { process_id: processId } : {}),
  defaultOptions: { staleTime: 30_000 },
});

/**
 * Carga el listado de documentos. Si se aporta `processId`, el filtro se aplica
 * en la API (acota al proceso activo del aside).
 *
 * Wrapper de TanStack Query que preserva la forma `{ documents, loading, error,
 * reload }` consumida por los componentes existentes.
 */
export function useDocuments(processId?: string): {
  documents: Document[];
  loading: boolean;
  error: Error | null;
  reload: () => Promise<void>;
} {
  const { hasPermission } = useUserProfile();
  const canIndex = hasPermission(DMS_PERMISSIONS.documentIndex);
  const query = useDocumentsQuery(processId, { enabled: canIndex });

  return {
    documents: query.data ?? [],
    loading: query.isLoading,
    error: query.error ?? null,
    reload: async () => {
      await query.refetch();
    },
  };
}
