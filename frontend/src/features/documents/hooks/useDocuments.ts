import { useEffect, useState } from 'react';
import { fetchDocuments } from '../../../api/documents';
import type { Document } from '../../../types/documents';

/**
 * Carga el listado de documentos una vez al montar.
 * El filtrado se realiza en el cliente (sin llamadas extra a la API).
 */
export function useDocuments(): {
  documents: Document[];
  loading: boolean;
  error: Error | null;
  reload: () => Promise<void>;
} {
  const [documents, setDocuments] = useState<Document[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);

  const load = async () => {
    try {
      setLoading(true);
      const data = await fetchDocuments();
      setDocuments(data);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err : new Error('Unknown error'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    let cancelled = false;
    const loadOnMount = async () => {
      try {
        setLoading(true);
        const data = await fetchDocuments();
        if (!cancelled) {
          setDocuments(data);
          setError(null);
        }
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof Error ? err : new Error('Unknown error'));
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    void loadOnMount();

    return () => {
      cancelled = true;
    };
  }, []);

  return {
    documents,
    loading,
    error,
    reload: load,
  };
}
