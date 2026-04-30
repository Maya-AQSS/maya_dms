import type { Process } from '../types/processes';
import { apiGetJson } from './http';

/** GET /api/v1/processes — catálogo de procesos disponibles. */
export async function fetchProcesses(): Promise<{ data: Process[] }> {
  return apiGetJson<{ data: Process[] }>('processes');
}
