import { useCallback, useEffect, useState } from 'react';
import { ApiHttpError } from '../../../api/http';
import {
  archiveTheme as archiveThemeRequest,
  cloneTheme as cloneThemeRequest,
  createTheme as createThemeRequest,
  deleteTheme as deleteThemeRequest,
  fetchThemes,
  publishTheme as publishThemeRequest,
  updateTheme as updateThemeRequest,
} from '../../../api/themes';
import type {
  CloneThemePayload,
  CreateThemePayload,
  UpdateThemePayload,
} from '../../../api/themes';
import type { Theme, ThemeListFilters } from '../../../types/themes';

function formatActionError(err: unknown): string {
  if (err instanceof ApiHttpError) {
    if (err.status === 403) {
      return err.message?.trim() || 'No tienes permiso para esta acción sobre el theme.';
    }
    if (err.status === 401) {
      return 'Sesión no válida o token ausente.';
    }
    if (err.status === 422) {
      return err.message || 'Datos no válidos; revisa colores y campos obligatorios.';
    }
    return err.message || `Error HTTP ${err.status}`;
  }
  return err instanceof Error ? err.message : 'Error desconocido';
}

/**
 * Listado y mutaciones de themes. Mismo patrón que useTemplates — paginación
 * en cliente sobre la respuesta paginada del backend (15 ítems / página).
 */
export function useThemes(initialFilters: ThemeListFilters = {}) {
  const [items, setItems] = useState<Theme[]>([]);
  const [meta, setMeta] = useState<{ current_page: number; per_page: number; total: number; last_page: number } | null>(
    null,
  );
  const [filters, setFilters] = useState<ThemeListFilters>(initialFilters);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionInfo, setActionInfo] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setListError(null);
      setLoading(true);
      const res = await fetchThemes(filters);
      setItems(res.data);
      setMeta(res.meta);
    } catch (e) {
      setListError(formatActionError(e));
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [filters]);

  useEffect(() => {
    void load();
  }, [load]);

  const applyFilters = useCallback((patch: Partial<ThemeListFilters>) => {
    setFilters((f) => {
      const next = { ...f, ...patch };
      if (!Object.prototype.hasOwnProperty.call(patch, 'page')) {
        next.page = 1;
      }
      return next;
    });
  }, []);

  const goToPage = useCallback((page: number) => {
    setFilters((f) => ({ ...f, page: Math.max(1, page) }));
  }, []);

  const createTheme = useCallback(
    async (payload: CreateThemePayload): Promise<Theme> => {
      try {
        setActionError(null);
        setActionInfo(null);
        const created = await createThemeRequest(payload);
        setActionInfo('Theme creado correctamente.');
        await load();
        return created;
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

  const updateTheme = useCallback(
    async (id: string, payload: UpdateThemePayload): Promise<Theme> => {
      try {
        setActionError(null);
        setActionInfo(null);
        const updated = await updateThemeRequest(id, payload);
        setActionInfo('Cambios guardados.');
        await load();
        return updated;
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

  const publishTheme = useCallback(
    async (id: string): Promise<Theme> => {
      try {
        setActionError(null);
        setActionInfo(null);
        const published = await publishThemeRequest(id);
        setActionInfo('Theme publicado.');
        await load();
        return published;
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

  const archiveTheme = useCallback(
    async (id: string): Promise<Theme> => {
      try {
        setActionError(null);
        setActionInfo(null);
        const archived = await archiveThemeRequest(id);
        setActionInfo('Theme archivado.');
        await load();
        return archived;
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

  const deleteTheme = useCallback(
    async (id: string) => {
      try {
        setActionError(null);
        setActionInfo(null);
        await deleteThemeRequest(id);
        setActionInfo('Theme eliminado.');
        await load();
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

  const cloneTheme = useCallback(
    async (id: string, payload: CloneThemePayload = {}): Promise<Theme> => {
      try {
        setActionError(null);
        setActionInfo(null);
        const cloned = await cloneThemeRequest(id, payload);
        setActionInfo('Copia creada como borrador.');
        await load();
        return cloned;
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

  return {
    items,
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
    createTheme,
    updateTheme,
    publishTheme,
    archiveTheme,
    deleteTheme,
    cloneTheme,
  };
}
