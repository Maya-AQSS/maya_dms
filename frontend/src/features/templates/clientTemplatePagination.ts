import type { TemplatesListMeta } from '../../types/templates';

/** Paginación en cliente sobre la lista completa devuelta por la API. */
export function sliceTemplatesPage<T>(items: T[], page: number, perPage: number): T[] {
  const p = Math.max(1, page);
  const pp = Math.max(1, perPage);
  return items.slice((p - 1) * pp, p * pp);
}

export function buildTemplatesListMeta(total: number, page: number, perPage: number): TemplatesListMeta {
  const pp = Math.max(1, perPage);
  const lastPage = Math.max(1, Math.ceil(total / pp));
  const current = Math.min(Math.max(1, page), lastPage);
  return {
    current_page: current,
    last_page: lastPage,
    per_page: pp,
    total,
  };
}
