import { useCallback, useEffect, useState } from 'react';
import { fetchDocuments } from '../../../api/documents';
/**
 * Carga el listado de documentos una vez al montar.
 * El filtrado se realiza en el cliente (sin llamadas extra a la API).
 */
export function useDocuments() {
    const [documents, setDocuments] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const load = useCallback(async () => {
        try {
            setLoading(true);
            const data = await fetchDocuments();
            setDocuments(data);
            setError(null);
        }
        catch (err) {
            setError(err instanceof Error ? err : new Error('Unknown error'));
        }
        finally {
            setLoading(false);
        }
    }, []);
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
