import { useCallback, useEffect, useMemo, useState } from 'react';
import { mapApiErrorToI18nKey } from '@ceedcv-maya/shared-auth-react';
import { useServerTable } from '@ceedcv-maya/shared-hooks-react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import {
  cloneTemplate as cloneTemplateRequest,
  deleteTemplate as deleteTemplateRequest,
  fetchTemplatesPage,
} from '../../../api/templates';
import { useUserProfile } from '../../../features/user-profile';
import { useFavoritesIds } from '../../../hooks/useFavoritesIds';
import { DEFAULT_TABLE_PAGE_SIZE, dropInvalidStoredPageSize } from '../../../lib/dataTablePageSize';
import { NO_MATCH_ID } from '../../../lib/noMatchId';
import { DMS_PERMISSIONS } from '../../../permissions';
import type { Template, TemplatesListMeta } from '../../../types/templates';

/** Columnas ordenables server-side (espejo de la whitelist del backend). */
const SORTABLE_TEMPLATE_COLUMNS = ['name', 'delivery_deadline', 'created_at', 'updated_at'] as const;

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
  const { t } = useTranslation('templates');
  const navigate = useNavigate();
  const { hasPermission } = useUserProfile();
  const canIndex = hasPermission(DMS_PERMISSIONS.templateIndex);
  const { templateIds: favoriteTemplateIds } = useFavoritesIds();

  // Mapeo de errores delegado al helper compartido (mapApiErrorToI18nKey) +
  // keys locales `errors.*` del namespace templates (es/va/en). CAMBIO FUNCIONAL
  // (ver changes.md): antes se preferia el `message` del backend en 403/422.
  const formatListError = useCallback(
    (err: unknown): string => {
      const key = mapApiErrorToI18nKey(err, 'errors', 'errorUnknown');
      // mapApiErrorToI18nKey devuelve `string`; las keys existen en templates.json
      // pero el tipado estricto de i18next exige un literal conocido.
      return t(key as 'errors.errorUnknown');
    },
    [t],
  );

  dropInvalidStoredPageSize(storageKey);
  const table = useServerTable<Record<TemplateFilterKeys, string>>({
    defaults: { ...TEMPLATE_FILTER_DEFAULTS },
    sortableColumns: SORTABLE_TEMPLATE_COLUMNS,
    storageKey,
    defaultSort: { columnId: 'updated_at', direction: 'desc' },
    defaultPageSize: DEFAULT_TABLE_PAGE_SIZE,
  });

  const [rows, setRows] = useState<Template[]>([]);
  const [meta, setMeta] = useState<TemplatesListMeta | null>(null);
  const [loading, setLoading] = useState(true);
  // Tipo unificado con useServerDocumentsTable (D1/D7): el componente formatea
  // el mensaje vía i18n con `message` (igual en ambos dominios).
  const [listError, setListError] = useState<Error | null>(null);
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
        setListError(e instanceof Error ? e : new Error('Error desconocido'));
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
    [refetch, formatListError],
  );

  const cloneTemplate = useCallback(
    async (id: string) => {
      try {
        setActionError(null);
        setActionInfo(null);
        const newTemplate = await cloneTemplateRequest(id);
        setActionInfo('Copia en borrador creada con el sufijo «(copia)».');
        navigate(`/templates/${newTemplate.id}/edit`);
      } catch (e) {
        setActionError(formatListError(e));
        throw e;
      }
    },
    [formatListError, navigate],
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
