import { useCallback, useEffect, useState } from 'react';
import { fetchFavorites } from '../api/favorites';

/**
 * IDs de plantillas y documentos favoritos del usuario (GET /favorites).
 * Se recarga al montar y al recuperar el foco de la ventana (p. ej. vuelta desde preview).
 */
export function useFavoritesIds(): {
  templateIds: ReadonlySet<string>;
  documentIds: ReadonlySet<string>;
  loading: boolean;
  refetch: () => Promise<void>;
} {
  const [templateIds, setTemplateIds] = useState<Set<string>>(() => new Set());
  const [documentIds, setDocumentIds] = useState<Set<string>>(() => new Set());
  const [loading, setLoading] = useState(true);

  const refetch = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await fetchFavorites();
      setTemplateIds(new Set(data.template_ids));
      setDocumentIds(new Set(data.document_ids));
    } catch {
      setTemplateIds(new Set());
      setDocumentIds(new Set());
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void refetch();
  }, [refetch]);

  useEffect(() => {
    const onFocus = () => {
      void refetch();
    };
    window.addEventListener('focus', onFocus);
    return () => window.removeEventListener('focus', onFocus);
  }, [refetch]);

  return { templateIds, documentIds, loading, refetch };
}
