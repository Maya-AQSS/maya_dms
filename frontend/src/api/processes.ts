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

/** DELETE /api/v1/processes/:id */
export async function deleteProcess(id: string): Promise<void> {
  await apiFetchJson<undefined>(`processes/${id}`, { method: 'DELETE' });
}
