import type { Process, ProcessDeletionPreview } from '../types/processes';
import { apiFetchJson, apiGetJson } from './http';
import { normalizePaginatedResponse } from './paginatedList';

/** Metadatos de paginación server-side de procesos. */
export type ProcessesListMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type ProcessesPageResponse = {
  data: Process[];
  meta: ProcessesListMeta;
};

export type ProcessListFilters = {
  search?: string;
  parent_id?: string;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
};

function buildProcessesListQuery(filters: ProcessListFilters): string {
  const q = new URLSearchParams();
  if (filters.search) q.set('search', filters.search);
  if (filters.parent_id) q.set('parent_id', filters.parent_id);
  if (filters.sort_by) q.set('sort_by', filters.sort_by);
  if (filters.sort_dir) q.set('sort_dir', filters.sort_dir);
  if (filters.page) q.set('page', String(filters.page));
  if (filters.per_page) q.set('per_page', String(filters.per_page));
  const s = q.toString();
  return s ? `?${s}` : '';
}

/**
 * GET /api/v1/processes — una sola página (server-side: filtros, sort y paginación
 * los resuelve el backend). Usado por la tabla de procesos vía useServerTable.
 */
export async function fetchProcessesPage(
  filters: ProcessListFilters = {},
): Promise<ProcessesPageResponse> {
  const body = await apiGetJson<unknown>(`processes${buildProcessesListQuery(filters)}`);
  const page = normalizePaginatedResponse<Process>(body);
  return {
    data: page.data,
    meta: {
      current_page: page.current_page,
      last_page: page.last_page,
      per_page: page.per_page,
      total: page.total,
    },
  };
}

/** GET /api/v1/processes — catálogo de procesos disponibles (sin paginación, para compatibilidad). */
export async function fetchProcesses(): Promise<{ data: Process[] }> {
  return apiGetJson<{ data: Process[] }>('processes');
}

/** GET /api/v1/processes/:id */
export async function fetchProcess(id: string): Promise<{ data: Process }> {
  return apiGetJson<{ data: Process }>(`processes/${id}`);
}

export type ProcessPayload = {
  code: string;
  name: string;
  alias: string;
  description?: string | null;
  process_parent_id?: string | null;
  color?: string | null;
  icon?: string | null;
};

/** POST /api/v1/processes */
export async function createProcess(payload: ProcessPayload): Promise<{ data: Process }> {
  return apiFetchJson<{ data: Process }>('processes', { method: 'POST', body: payload });
}

/** PATCH /api/v1/processes/:id */
export async function updateProcess(id: string, payload: ProcessPayload): Promise<{ data: Process }> {
  return apiFetchJson<{ data: Process }>(`processes/${id}`, { method: 'PATCH', body: payload });
}

/** GET /api/v1/processes/:id/deletion-preview — conteo de dependientes afectados. */
export async function fetchProcessDeletionPreview(
  id: string,
): Promise<{ data: ProcessDeletionPreview }> {
  return apiGetJson<{ data: ProcessDeletionPreview }>(`processes/${id}/deletion-preview`);
}

/** DELETE /api/v1/processes/:id */
export async function deleteProcess(id: string): Promise<void> {
  await apiFetchJson<undefined>(`processes/${id}`, { method: 'DELETE' });
}
