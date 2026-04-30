import { useCallback, useEffect, useState } from 'react';
import { ApiHttpError } from '../../../api/http';
import {
  cloneTemplate as cloneTemplateRequest,
  createTemplate as createTemplateRequest,
  deleteTemplate as deleteTemplateRequest,
  fetchTemplates,
  updateTemplate as updateTemplateRequest,
} from '../../../api/templates';
import type { CreateTemplatePayload, UpdateTemplatePayload } from '../../../api/templates';
import type { Template, TemplateListFilters, TemplatesListMeta } from '../../../types/templates';

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

/**
 * Listado y mutaciones de plantillas normativas (filtros + paginación acotada a 10).
 */
export function useTemplates() {
  const [templates, setTemplates] = useState<Template[]>([]);
  const [meta, setMeta] = useState<TemplatesListMeta | null>(null);
  const [filters, setFilters] = useState<TemplateListFilters>({ per_page: DEFAULT_PER_PAGE });
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionInfo, setActionInfo] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setListError(null);
      setLoading(true);
      const res = await fetchTemplates({
        ...filters,
        per_page: filters.per_page ?? DEFAULT_PER_PAGE,
      });
      setTemplates(res.data);
      setMeta(res.meta);
    } catch (e) {
      setListError(formatActionError(e));
      setTemplates([]);
      setMeta(null);
    } finally {
      setLoading(false);
    }
  }, [filters]);

  useEffect(() => {
    void load();
  }, [load]);

  const applyFilters = useCallback((patch: Partial<TemplateListFilters>) => {
    setFilters((f) => ({ ...f, ...patch, page: 1 }));
  }, []);

  const goToPage = useCallback((page: number) => {
    setFilters((f) => ({ ...f, page: Math.max(1, page) }));
  }, []);

  const createTemplate = useCallback(
    async (payload: CreateTemplatePayload) => {
      try {
        setActionError(null);
        setActionInfo(null);
        const res = await createTemplateRequest(payload);
        setActionInfo('Plantilla creada correctamente.');
        await load();
        return res.data;
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
