import { useCallback, useEffect, useMemo, useState } from 'react';
import { useServerTable } from '@ceedcv-maya/shared-hooks-react';
import { fetchDocumentsPage, type DocumentsListMeta } from '../../../api/documents';
import { DEFAULT_TABLE_PAGE_SIZE, dropInvalidStoredPageSize } from '../../../lib/dataTablePageSize';
import { useUserProfile } from '../../../features/user-profile';
import { useFavoritesIds } from '../../../hooks/useFavoritesIds';
import { DMS_PERMISSIONS } from '../../../permissions';
import type { Document } from '../../../types/documents';

/** Columnas ordenables server-side (espejo de la whitelist del backend). */
const SORTABLE_DOCUMENT_COLUMNS = ['title', 'status', 'delivery_deadline', 'created_at', 'updated_at'] as const;

/** Sentinela que no coincide con ningún id (para "solo favoritos" sin favoritos → 0 resultados). */
const NO_MATCH_ID = '00000000-0000-0000-0000-000000000000';

/** Filtros de dominio sincronizados a URL (claves = query params del backend, salvo `favorites`). */
const DOCUMENT_FILTER_DEFAULTS = {
  status: '',
  search: '',
  /** Contexto académico estructurado en cascada (server-side sobre el snapshot del cabezal). */
  study_type_id: '',
  study_id: '',
  module_id: '',
  /** Flag UI: '' = todos; 'favorites' = solo favoritos (se traduce a `favorite_ids` server-side). */
  favorites: '',
} as const;

type DocumentFilterKeys = keyof typeof DOCUMENT_FILTER_DEFAULTS;

/**
 * Listado server-side de documentos: filtros + paginación + ordenación los resuelve
 * el backend (estándar unificado, ver useServerTable). Estado de tabla en URL
 * (filtros/page/sort) y localStorage (per_page).
 *
 * @param processId Filtro `process_id` permanente del contexto de la URL.
 */
export function useServerDocumentsTable(processId?: string) {
  const { hasPermission } = useUserProfile();
  const canIndex = hasPermission(DMS_PERMISSIONS.documentIndex);
  const { documentIds: favoriteDocumentIds } = useFavoritesIds();

  dropInvalidStoredPageSize('maya:dms:documents-table');
  const table = useServerTable<Record<DocumentFilterKeys, string>>({
    defaults: { ...DOCUMENT_FILTER_DEFAULTS },
    sortableColumns: SORTABLE_DOCUMENT_COLUMNS,
    storageKey: 'maya:dms:documents-table',
    defaultSort: { columnId: 'created_at', direction: 'desc' },
    defaultPageSize: DEFAULT_TABLE_PAGE_SIZE,
  });

  const [rows, setRows] = useState<Document[]>([]);
  const [meta, setMeta] = useState<DocumentsListMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);
  // Token para forzar refetch tras mutaciones externas (p. ej. crear documento).
  const [refetchToken, setRefetchToken] = useState(0);
  const refetch = useCallback(() => setRefetchToken((n) => n + 1), []);

  const favoriteIdsCsv = useMemo(() => [...favoriteDocumentIds].join(','), [favoriteDocumentIds]);

  // El flag `favorites` se traduce a `favorite_ids` (ids de documento server-side).
  const apiParams = useMemo(() => {
    const { favorites, ...rest } = table.queryParams;
    const params: Record<string, unknown> = { ...rest };
    if (processId) params.process_id = processId;
    if (favorites === 'favorites') {
      params.favorite_ids = favoriteIdsCsv || NO_MATCH_ID;
    }
    return params;
  }, [table.queryParams, processId, favoriteIdsCsv]);
  const apiParamsKey = JSON.stringify(apiParams);

  useEffect(() => {
    if (!canIndex) {
      setRows([]);
      setMeta(null);
      setLoading(false);
      setError(null);
      return;
    }
    let cancelled = false;
    setLoading(true);
    setError(null);
    fetchDocumentsPage(apiParams as Parameters<typeof fetchDocumentsPage>[0])
      .then((res) => {
        if (cancelled) return;
        setRows(Array.isArray(res.data) ? res.data : []);
        setMeta(res.meta ?? null);
      })
      .catch((e) => {
        if (cancelled) return;
        setRows([]);
        setMeta(null);
        setError(e instanceof Error ? e : new Error('Error desconocido'));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [apiParamsKey, canIndex, refetchToken]);

  return { ...table, rows, meta, loading, error, canIndex, refetch };
}
