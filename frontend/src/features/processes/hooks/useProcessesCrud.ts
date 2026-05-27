import { useCallback, useEffect, useState } from 'react';
import { ApiHttpError } from '../../../api/http';
import {
  fetchProcessesPaginated,
  createProcess,
  updateProcess,
  deleteProcess,
} from '../../../api/processes';
import type { ProcessListFilters, ProcessListMeta, ProcessPayload } from '../../../api/processes';
import type { Process } from '../../../types/processes';

export type { ProcessPayload };

function formatError(err: unknown): string {
  if (err instanceof ApiHttpError) {
    if (err.status === 409)
      return (
        err.message ||
        'El proceso tiene subprocesos, plantillas o documentos asociados y no puede eliminarse.'
      );
    if (err.status === 422) return err.message || 'Datos no válidos.';
    if (err.status === 403) return err.message || 'Sin permiso para esta acción.';
    return err.message || `Error HTTP ${err.status}`;
  }
  return err instanceof Error ? err.message : 'Error desconocido';
}

export function useProcessesCrud(initialFilters: ProcessListFilters = {}) {
  const [items, setItems] = useState<Process[]>([]);
  const [meta, setMeta] = useState<ProcessListMeta | null>(null);
  const [filters, setFilters] = useState<ProcessListFilters>({
    page: 1,
    per_page: 20,
    ...initialFilters,
  });
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionInfo, setActionInfo] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setListError(null);
      setLoading(true);
      const res = await fetchProcessesPaginated(filters);
      setItems(res.data);
      setMeta(res.meta);
    } catch (e) {
      setListError(formatError(e));
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [filters]);

  useEffect(() => {
    void load();
  }, [load]);

  const applyFilters = useCallback((patch: Partial<ProcessListFilters>) => {
    setFilters((f) => {
      const next = { ...f, ...patch };
      if (!Object.prototype.hasOwnProperty.call(patch, 'page')) next.page = 1;
      return next;
    });
  }, []);

  const goToPage = useCallback((page: number) => {
    setFilters((f) => ({ ...f, page: Math.max(1, page) }));
  }, []);

  const createProcessItem = useCallback(
    async (payload: ProcessPayload): Promise<Process> => {
      try {
        setActionError(null);
        setActionInfo(null);
        const res = await createProcess(payload);
        setActionInfo('Proceso creado correctamente.');
        await load();
        return res.data;
      } catch (e) {
        setActionError(formatError(e));
        throw e;
      }
    },
    [load],
  );

  const updateProcessItem = useCallback(
    async (id: string, payload: ProcessPayload): Promise<Process> => {
      try {
        setActionError(null);
        setActionInfo(null);
        const res = await updateProcess(id, payload);
        setActionInfo('Cambios guardados.');
        await load();
        return res.data;
      } catch (e) {
        setActionError(formatError(e));
        throw e;
      }
    },
    [load],
  );

  const deleteProcessItem = useCallback(
    async (id: string): Promise<void> => {
      try {
        setActionError(null);
        setActionInfo(null);
        await deleteProcess(id);
        setActionInfo('Proceso eliminado.');
        await load();
      } catch (e) {
        setActionError(formatError(e));
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
    createProcess: createProcessItem,
    updateProcess: updateProcessItem,
    deleteProcess: deleteProcessItem,
  };
}
