import { useCallback, useEffect, useMemo, useState } from 'react';
import { ApiHttpError } from '../../../api/http';
import {
  cloneTemplate as cloneTemplateRequest,
  createTemplate as createTemplateRequest,
  deleteTemplate as deleteTemplateRequest,
  fetchTemplates,
  updateTemplate as updateTemplateRequest,
} from '../../../api/templates';
import type { CreateTemplatePayload, UpdateTemplatePayload } from '../../../api/templates';
import { useUserProfile } from '../../../features/user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import type { Template, TemplateListFilters } from '../../../types/templates';
import { buildTemplatesListMeta, sliceTemplatesPage } from '../clientTemplatePagination';

function formatActionError(err: unknown): string {
  if (err instanceof ApiHttpError) {
    if (err.status === 403) {
      const fromApi = err.message?.trim();
      if (fromApi) {
        return fromApi;
      }
      return 'No tienes permiso para esta acción (p. ej. visibilidad compartida solo para coordinación).';
    }
    if (err.status === 401) {
      return 'Sesión no válida o token ausente.';
    }
    if (err.status === 422) {
      return err.message || 'Datos no válidos; revisa visibilidad y campos obligatorios.';
    }
    return err.message || `Error HTTP ${err.status}`;
  }
  return err instanceof Error ? err.message : 'Error desconocido';
}

const DEFAULT_PER_PAGE = 10;

/** Columnas de tabla que admiten ordenación local (no incluye «estado»). */
const SORTABLE_TEMPLATE_COLUMN_IDS = new Set(['name', 'delivery_deadline']);

export type TemplatesTableSort = { columnId: string; direction: 'asc' | 'desc' } | null;

/**
 * Listado y mutaciones de plantillas normativas.
 * La API pagina en servidor (ADR-C); el cliente agrega páginas y pagina/filtra en tabla.
 *
 * @param processId Si se aporta, se aplica como filtro `process_id` permanente
 *   (no se expone en el panel de filtros — viene del contexto de la URL).
 * @param sortBy Orden local solo para columnas en {@see SORTABLE_TEMPLATE_COLUMN_IDS}.
 */
export function useTemplates(processId?: string, sortBy?: TemplatesTableSort) {
  const { hasPermission } = useUserProfile();
  const canIndex = hasPermission(DMS_PERMISSIONS.templateIndex);
  const [fullList, setFullList] = useState<Template[]>([]);
  /** Sin `per_page` por defecto: las pantallas usan `filters.per_page ?? pageSize` (preferencias de tabla). */
  const [filters, setFilters] = useState<TemplateListFilters>({});
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionInfo, setActionInfo] = useState<string | null>(null);

  const filtersForApi = useMemo(() => {
    const { page: _page, ...rest } = filters;
    return {
      ...rest,
      ...(processId ? { process_id: processId } : {}),
    };
  }, [
    filters.visibility_level,
    filters.status,
    filters.study_type_id,
    filters.study_id,
    filters.module_id,
    filters.team_id,
    filters.author_name,
    filters.delivery_deadline,
    filters.published_on,
    filters.per_page,
    filters.process_id,
    processId,
  ]);

  const page = filters.page ?? 1;
  const perPage = filters.per_page ?? DEFAULT_PER_PAGE;

  const sortedList = useMemo(() => {
    const list = Array.isArray(fullList) ? fullList : [];
    if (!sortBy || !SORTABLE_TEMPLATE_COLUMN_IDS.has(sortBy.columnId)) {
      return list;
    }
    const { columnId, direction } = sortBy;
    const dir = direction === 'asc' ? 1 : -1;

    return [...list].sort((a, b) => {
      if (columnId === 'name') {
        return (a.name ?? '').localeCompare(b.name ?? '', 'es') * dir;
      }
      if (columnId === 'delivery_deadline') {
        const valA = a.status === 'published' ? '9999-12-31' : (a.delivery_deadline ?? '').slice(0, 10);
        const valB = b.status === 'published' ? '9999-12-31' : (b.delivery_deadline ?? '').slice(0, 10);
        if (valA < valB) {
          return -1 * dir;
        }
        if (valA > valB) {
          return 1 * dir;
        }

        return 0;
      }

      return 0;
    });
  }, [fullList, sortBy]);

  const templates = useMemo(
    () => sliceTemplatesPage(sortedList, page, perPage),
    [sortedList, page, perPage],
  );

  const meta = useMemo(
    () => buildTemplatesListMeta(sortedList.length, page, perPage),
    [sortedList.length, page, perPage],
  );

  const load = useCallback(async () => {
    if (!canIndex) {
      setListError(null);
      setFullList([]);
      setLoading(false);
      return;
    }

    try {
      setListError(null);
      setLoading(true);
      const res = await fetchTemplates(filtersForApi);
      setFullList(Array.isArray(res.data) ? res.data : []);
    } catch (e) {
      setListError(formatActionError(e));
      setFullList([]);
    } finally {
      setLoading(false);
    }
  }, [canIndex, filtersForApi]);

  useEffect(() => {
    void load();
  }, [load]);

  const applyFilters = useCallback((patch: Partial<TemplateListFilters>) => {
    setFilters((f) => {
      const next = { ...f, ...patch };
      // Solo reiniciar a la página 1 cuando el cambio no fija ya `page` (p. ej. corrección al acortar el listado).
      if (!Object.prototype.hasOwnProperty.call(patch, 'page')) {
        next.page = 1;
      }
      return next;
    });
  }, []);

  const goToPage = useCallback((page: number) => {
    setFilters((f) => ({ ...f, page: Math.max(1, page) }));
  }, []);

  const createTemplate = useCallback(
    async (payload: CreateTemplatePayload) => {
      try {
        setActionError(null);
        setActionInfo(null);
        const created = await createTemplateRequest(payload);
        setActionInfo('Plantilla creada correctamente.');
        await load();
        return created;
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

  const updateTemplate = useCallback(
    async (id: string, payload: UpdateTemplatePayload) => {
      try {
        setActionError(null);
        setActionInfo(null);
        await updateTemplateRequest(id, payload);
        setActionInfo('Cambios guardados.');
        await load();
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

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
        await load();
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

  const cloneTemplate = useCallback(
    async (id: string) => {
      try {
        setActionError(null);
        setActionInfo(null);
        await cloneTemplateRequest(id);
        setActionInfo('Copia en borrador creada con el sufijo «(copia)».');
        await load();
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

  return {
    templates,
    /** Catálogo completo: orden API y, si aplica, orden local por nombre o fecha de validación. */
    catalogSorted: sortedList,
    meta,
    filters,
    loading,
    listError,
    actionError,
    actionInfo,
    clearActionError: () => setActionError(null),
    clearActionInfo: () => setActionInfo(null),
    refetch: load,
    applyFilters,
    goToPage,
    createTemplate,
    updateTemplate,
    deleteTemplate,
    cloneTemplate,
  };
}
