import type { Process } from '../types/processes';
import { apiFetchJson, apiGetJson } from './http';

/** GET /api/v1/processes — catálogo de procesos disponibles. */
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
};

/** POST /api/v1/processes */
export async function createProcess(payload: ProcessPayload): Promise<{ data: Process }> {
  return apiFetchJson<{ data: Process }>('processes', { method: 'POST', body: payload });
}

/** PATCH /api/v1/processes/:id */
export async function updateProcess(id: string, payload: ProcessPayload): Promise<{ data: Process }> {
  return apiFetchJson<{ data: Process }>(`processes/${id}`, { method: 'PATCH', body: payload });
}

/** DELETE /api/v1/processes/:id */
export async function deleteProcess(id: string): Promise<void> {
  await apiFetchJson<undefined>(`processes/${id}`, { method: 'DELETE' });
}

export type ProcessListFilters = {
  search?: string;
  parent_id?: string; // UUID o 'root'
  page?: number;
  per_page?: number;
};

export type ProcessListMeta = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};

export type ProcessesListResponse = {
  data: Process[];
  meta: ProcessListMeta;
};

function buildProcessListQuery(filters: ProcessListFilters): string {
  const q = new URLSearchParams();
  if (filters.search) q.set('search', filters.search);
  if (filters.parent_id) q.set('parent_id', filters.parent_id);
  if (filters.page) q.set('page', String(filters.page));
  if (filters.per_page) q.set('per_page', String(filters.per_page));
  const qs = q.toString();
  return qs ? `?${qs}` : '';
}

/** GET /api/v1/processes?search=&parent_id=&page= — lista paginada con filtros */
export async function fetchProcessesPaginated(
  filters: ProcessListFilters = {},
): Promise<ProcessesListResponse> {
  return apiGetJson<ProcessesListResponse>(
    `processes${buildProcessListQuery({ ...filters, page: filters.page ?? 1 })}`,
  );
}
