import { useCallback, useEffect, useMemo, useState } from 'react';
import { useServerTable } from '@ceedcv-maya/shared-hooks-react';
import { ApiHttpError } from '../../../api/http';
import {
  cloneTemplate as cloneTemplateRequest,
  deleteTemplate as deleteTemplateRequest,
  fetchTemplatesPage,
} from '../../../api/templates';
import { useUserProfile } from '../../../features/user-profile';
import { useFavoritesIds } from '../../../hooks/useFavoritesIds';
import { DMS_PERMISSIONS } from '../../../permissions';
import type { Template, TemplatesListMeta } from '../../../types/templates';

/** Columnas ordenables server-side (espejo de la whitelist del backend). */
const SORTABLE_TEMPLATE_COLUMNS = ['name', 'delivery_deadline', 'created_at', 'updated_at'] as const;

/** Sentinela que no coincide con ningún id (para "solo favoritos" sin favoritos → 0 resultados). */
const NO_MATCH_ID = '00000000-0000-0000-0000-000000000000';

/** Filtros de dominio sincronizados a URL (claves = query params del backend, salvo `favorites`). */
const TEMPLATE_FILTER_DEFAULTS = {
  status: '',
  visibility_level: '',
  study_type_id: '',
  study_id: '',
  module_id: '',
  team_id: '',
  search: '',
  /** Flag UI: '' = todos; 'favorites' = solo favoritos (se traduce a `favorite_ids` server-side). */
  favorites: '',
} as const;

type TemplateFilterKeys = keyof typeof TEMPLATE_FILTER_DEFAULTS;

function formatListError(err: unknown): string {
  if (err instanceof ApiHttpError) {
    if (err.status === 403) {
      return err.message?.trim() || 'No tienes permiso para esta acción.';
    }
    if (err.status === 401) return 'Sesión no válida o token ausente.';
    if (err.status === 422) return err.message || 'Datos no válidos.';
    return err.message || `Error HTTP ${err.status}`;
  }
  return err instanceof Error ? err.message : 'Error desconocido';
}

/**
 * Listado server-side de plantillas: filtros + paginación + ordenación los resuelve
 * el backend (estándar unificado, ver useServerTable). El estado de tabla vive en la
 * URL (filtros/page/sort) y localStorage (per_page).
 *
 * @param processId Filtro `process_id` permanente del contexto de la URL (no en el panel).
 * @param storageKey Clave de preferencias (per_page/columnas). Distinta por superficie.
 */
export function useServerTemplatesTable(
  processId?: string,
  storageKey = 'maya:dms:templates-table',
) {
  const { hasPermission } = useUserProfile();
  const canIndex = hasPermission(DMS_PERMISSIONS.templateIndex);
  const { templateIds: favoriteTemplateIds } = useFavoritesIds();

  const table = useServerTable<Record<TemplateFilterKeys, string>>({
    defaults: { ...TEMPLATE_FILTER_DEFAULTS },
    sortableColumns: SORTABLE_TEMPLATE_COLUMNS,
    storageKey,
    defaultSort: { columnId: 'updated_at', direction: 'desc' },
    defaultPageSize: 15,
  });

  const [rows, setRows] = useState<Template[]>([]);
  const [meta, setMeta] = useState<TemplatesListMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionInfo, setActionInfo] = useState<string | null>(null);

  const favoriteIdsCsv = useMemo(() => [...favoriteTemplateIds].join(','), [favoriteTemplateIds]);
  const refetch = useCallback(() => setReloadKey((k) => k + 1), []);

  // Params finales al backend: filtros de URL + page/per_page/sort + process_id de
  // contexto. El flag `favorites` se traduce a `favorite_ids` (ids server-side).
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
      setListError(null);
      return;
    }
    let cancelled = false;
    setLoading(true);
    setListError(null);
    fetchTemplatesPage(apiParams as Parameters<typeof fetchTemplatesPage>[0])
      .then((res) => {
        if (cancelled) return;
        setRows(Array.isArray(res.data) ? res.data : []);
        setMeta(res.meta ?? null);
      })
      .catch((e) => {
        if (cancelled) return;
        setRows([]);
        setMeta(null);
        setListError(formatListError(e));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [apiParamsKey, canIndex, reloadKey]);

  const deleteTemplate = useCallback(
    async (id: string) => {
      try {
        setActionError(null);
        setActionInfo(null);
        const r = await deleteTemplateRequest(id);
        if (r.hardDeleted) {
          setActionInfo('Plantilla eliminada.');
        } else if (r.data?.status === 'published') {
          setActionInfo('Borrador descartado. La plantilla vuelve a su versión publicada.');
        } else {
          setActionInfo('La plantilla tiene documentos asociados: se ha archivado en lugar de eliminarla.');
        }
        refetch();
      } catch (e) {
        setActionError(formatListError(e));
        throw e;
      }
    },
    [refetch],
  );

  const cloneTemplate = useCallback(
    async (id: string) => {
      try {
        setActionError(null);
        setActionInfo(null);
        await cloneTemplateRequest(id);
        setActionInfo('Copia en borrador creada con el sufijo «(copia)».');
        refetch();
      } catch (e) {
        setActionError(formatListError(e));
        throw e;
      }
    },
    [refetch],
  );

  return {
    ...table,
    rows,
    meta,
    loading,
    listError,
    canIndex,
    refetch,
    actionError,
    actionInfo,
    clearActionError: () => setActionError(null),
    clearActionInfo: () => setActionInfo(null),
    deleteTemplate,
    cloneTemplate,
  };
}
