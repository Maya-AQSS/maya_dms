import { useCallback, useEffect, useMemo, useState } from 'react';
import { useServerTable } from '@ceedcv-maya/shared-hooks-react';
import { fetchProcessesPage, type ProcessesListMeta } from '../../../api/processes';
import { DEFAULT_TABLE_PAGE_SIZE, dropInvalidStoredPageSize } from '../../../lib/dataTablePageSize';
import { useUserProfile } from '../../../features/user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import type { Process } from '../../../types/processes';

/** Columnas ordenables server-side (espejo de la whitelist del backend). */
const SORTABLE_PROCESS_COLUMNS = ['code', 'name', 'alias', 'created_at', 'updated_at'] as const;

/** Filtros de dominio sincronizados a URL. */
const PROCESS_FILTER_DEFAULTS = {
  search: '',
  parent_id: '',
} as const;

type ProcessFilterKeys = keyof typeof PROCESS_FILTER_DEFAULTS;

/**
 * Listado server-side de procesos: filtros + paginación + ordenación los resuelve
 * el backend (estándar unificado, ver useServerTable). Estado de tabla en URL
 * (filtros/page/sort) y localStorage (per_page).
 */
export function useServerProcessesTable() {
  const { hasPermission } = useUserProfile();
  const canIndex = hasPermission(DMS_PERMISSIONS.processIndex);

  dropInvalidStoredPageSize('maya:dms:processes-table');
  const table = useServerTable<Record<ProcessFilterKeys, string>>({
    defaults: { ...PROCESS_FILTER_DEFAULTS },
    sortableColumns: SORTABLE_PROCESS_COLUMNS,
    storageKey: 'maya:dms:processes-table',
    defaultSort: { columnId: 'code', direction: 'asc' },
    defaultPageSize: DEFAULT_TABLE_PAGE_SIZE,
  });

  const [rows, setRows] = useState<Process[]>([]);
  const [meta, setMeta] = useState<ProcessesListMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);
  const [refetchToken, setRefetchToken] = useState(0);
  const refetch = useCallback(() => setRefetchToken((n) => n + 1), []);

  const apiParams = useMemo(() => {
    return { ...table.queryParams };
  }, [table.queryParams]);
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
    fetchProcessesPage(apiParams as Parameters<typeof fetchProcessesPage>[0])
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
