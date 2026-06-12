import { useCallback, useEffect, useState } from 'react';
import { mapApiErrorToI18nKey } from '@ceedcv-maya/shared-auth-react';
import { useTranslation } from 'react-i18next';
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

/**
 * Listado y mutaciones de themes. Mismo patrón que useTemplates — paginación
 * en cliente sobre la respuesta paginada del backend (15 ítems / página).
 */
export function useThemes(initialFilters: ThemeListFilters = {}) {
  const { t } = useTranslation('themes');

  // Mapeo de errores delegado al helper compartido (mapApiErrorToI18nKey) +
  // keys locales `errors.*` del namespace themes (es/va/en). CAMBIO FUNCIONAL
  // (ver changes.md): antes se preferia el `message` del backend en 403/422.
  const formatActionError = useCallback(
    (err: unknown): string => {
      const key = mapApiErrorToI18nKey(err, 'errors', 'errorUnknown');
      // mapApiErrorToI18nKey devuelve `string`; las keys existen en themes.json
      // pero el tipado estricto de i18next exige un literal conocido.
      return t(key as 'errors.errorUnknown');
    },
    [t],
  );
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
  }, [filters, formatActionError]);

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
    [load, formatActionError],
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
    [load, formatActionError],
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
    [load, formatActionError],
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
    [load, formatActionError],
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
    [load, formatActionError],
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
    [load, formatActionError],
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
