import { useCallback, useEffect, useState } from 'react';
import { fetchDocuments } from '../../../api/documents';
import type { Document } from '../../../types/documents';

/**
 * Carga el listado de documentos una vez al montar.
 * El filtrado se realiza en el cliente (sin llamadas extra a la API).
 *
 * @param processId Si se aporta, se aplica como filtro `process_id` en la API
 *   (acota el listado al proceso activo del aside).
 */
export function useDocuments(processId?: string): {
  documents: Document[];
  loading: boolean;
  error: Error | null;
  reload: () => Promise<void>;
} {
  const [documents, setDocuments] = useState<Document[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const data = await fetchDocuments(processId ? { process_id: processId } : {});
      setDocuments(data);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err : new Error('Unknown error'));
    } finally {
      setLoading(false);
    }
  }, [processId]);

  useEffect(() => {
    void load();
  }, [load]);

  return {
    documents,
    loading,
    error,
    reload: load,
  };
}
