import { useCallback, useEffect, useMemo, useState } from 'react';
import { useServerTable } from '@ceedcv-maya/shared-hooks-react';
import { fetchTemplatesPage, type TemplateListFilters } from '../api/templates';
import { DEFAULT_TABLE_PAGE_SIZE, dropInvalidStoredPageSize } from '../lib/dataTablePageSize';
import type { Template } from '../types/templates';

/** Columnas ordenables server-side. */
const SORTABLE_NUEVA_PROGRAMACION_COLUMNS = ['name', 'latest_published_at'] as const;

/** Filtros de dominio sincronizados a URL. */
const NUEVA_PROGRAMACION_FILTER_DEFAULTS = {
  search: '',
  author_name: '',
  published_on: '',
} as const;

type NuevaProgramacionFilterKeys = keyof typeof NUEVA_PROGRAMACION_FILTER_DEFAULTS;

export interface TemplatesListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

/**
 * Listado server-side de plantillas para nueva programación:
 * filtros + paginación + ordenación los resuelve el backend.
 * Estado de tabla en URL (filtros/page/sort) y localStorage (per_page).
 */
export function useServerNuevaProgramacionTable(opts?: {
  usableForDocuments?: boolean;
  processId?: string;
}) {
  dropInvalidStoredPageSize('maya:dms:nueva-programacion-selector');
  const table = useServerTable<Record<NuevaProgramacionFilterKeys, string>>({
    defaults: { ...NUEVA_PROGRAMACION_FILTER_DEFAULTS },
    sortableColumns: SORTABLE_NUEVA_PROGRAMACION_COLUMNS,
    storageKey: 'maya:dms:nueva-programacion-selector',
    defaultSort: { columnId: 'latest_published_at', direction: 'desc' },
    defaultPageSize: DEFAULT_TABLE_PAGE_SIZE,
  });

  const [rows, setRows] = useState<Template[]>([]);
  const [meta, setMeta] = useState<TemplatesListMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);
  const [refetchToken, setRefetchToken] = useState(0);
  const refetch = useCallback(() => setRefetchToken((n) => n + 1), []);

  const apiParams = useMemo(() => {
    const filters: TemplateListFilters = {
      usable_for_documents: opts?.usableForDocuments ?? true,
    };
    if (table.filters.search) filters.search = table.filters.search;
    if (table.filters.author_name) filters.author_name = table.filters.author_name;
    if (table.filters.published_on) filters.published_on = table.filters.published_on;
    if (opts?.processId) filters.process_id = opts.processId;
    filters.page = table.page;
    filters.per_page = table.pageSize;
    if (table.sortBy) {
      filters.sort_by = table.sortBy.columnId;
      filters.sort_dir = table.sortBy.direction;
    }
    return filters;
  }, [table.filters, table.page, table.pageSize, table.sortBy, opts?.usableForDocuments, opts?.processId]);
  const apiParamsKey = JSON.stringify(apiParams);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    fetchTemplatesPage(apiParams)
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
  }, [apiParamsKey, refetchToken]);

  return { ...table, rows, meta, loading, error, refetch };
}
