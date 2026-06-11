import { useCallback, useEffect, useMemo, useState } from 'react';
import { useServerTable } from '@ceedcv-maya/shared-hooks-react';
import { fetchThemes, type ThemeListFilters } from '../../../api/themes';
import type { Theme } from '../../../types/themes';

/** Columnas ordenables server-side (espejo de la whitelist del backend). */
const SORTABLE_THEME_COLUMNS = ['name', 'created_at', 'updated_at'] as const;

/** Filtros de dominio sincronizados a URL. */
const THEME_FILTER_DEFAULTS = {
  search: '',
  status: '',
  team_id: '',
} as const;

type ThemeFilterKeys = keyof typeof THEME_FILTER_DEFAULTS;

export interface ThemesListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

/**
 * Listado server-side de temas: filtros + paginación + ordenación los resuelve
 * el backend (estándar unificado, ver useServerTable). Estado de tabla en URL
 * (filtros/page/sort) y localStorage (per_page).
 */
export function useServerThemesTable() {
  const table = useServerTable<Record<ThemeFilterKeys, string>>({
    defaults: { ...THEME_FILTER_DEFAULTS },
    sortableColumns: SORTABLE_THEME_COLUMNS,
    storageKey: 'maya:dms:themes-table',
    defaultSort: { columnId: 'updated_at', direction: 'desc' },
    defaultPageSize: 15,
  });

  const [rows, setRows] = useState<Theme[]>([]);
  const [meta, setMeta] = useState<ThemesListMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);
  const [refetchToken, setRefetchToken] = useState(0);
  const refetch = useCallback(() => setRefetchToken((n) => n + 1), []);

  const apiParams = useMemo(() => {
    const filters: ThemeListFilters = {};
    if (table.filters.search) filters.search = table.filters.search;
    if (table.filters.status) filters.status = table.filters.status as any;
    if (table.filters.team_id) filters.team_id = table.filters.team_id;
    filters.page = table.page;
    filters.per_page = table.pageSize;
    if (table.sortBy) {
      filters.sort_by = table.sortBy.columnId;
      filters.sort_dir = table.sortBy.direction;
    }
    return filters;
  }, [table.filters, table.page, table.pageSize, table.sortBy]);
  const apiParamsKey = JSON.stringify(apiParams);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    fetchThemes(apiParams)
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
