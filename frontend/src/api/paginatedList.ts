/**
 * Utilidades de listado paginado ADR-C (envelope plano Laravel).
 *
 * Copia local alineada con `shared-auth-react/src/pagination.ts` para no depender
 * del barrel del paquete en runtime (Vite a veces no resuelve `export *` en dev).
 */

export const PAGINATED_MAX_PER_PAGE = 100;

export type PaginatedListEnvelope<T> = {
  current_page: number;
  data: T[];
  first_page_url: string | null;
  from: number | null;
  last_page: number;
  last_page_url: string | null;
  links: unknown[];
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number | null;
  total: number;
};

export function normalizePaginatedResponse<T>(body: unknown): PaginatedListEnvelope<T> {
  if (Array.isArray(body)) {
    const total = body.length;

    return {
      current_page: 1,
      data: body as T[],
      first_page_url: null,
      from: total > 0 ? 1 : null,
      last_page: 1,
      last_page_url: null,
      links: [],
      next_page_url: null,
      path: '',
      per_page: Math.max(1, total),
      prev_page_url: null,
      to: total > 0 ? total : null,
      total,
    };
  }

  const raw = body as Partial<PaginatedListEnvelope<T>>;
  const data = Array.isArray(raw.data) ? raw.data : [];

  return {
    current_page: typeof raw.current_page === 'number' ? raw.current_page : 1,
    data,
    first_page_url: raw.first_page_url ?? null,
    from: raw.from ?? (data.length > 0 ? 1 : null),
    last_page: typeof raw.last_page === 'number' ? raw.last_page : 1,
    last_page_url: raw.last_page_url ?? null,
    links: Array.isArray(raw.links) ? raw.links : [],
    next_page_url: raw.next_page_url ?? null,
    path: typeof raw.path === 'string' ? raw.path : '',
    per_page: typeof raw.per_page === 'number' ? raw.per_page : Math.max(1, data.length),
    prev_page_url: raw.prev_page_url ?? null,
    to: raw.to ?? (data.length > 0 ? data.length : null),
    total: typeof raw.total === 'number' ? raw.total : data.length,
  };
}

export async function fetchAllPaginatedPages<T>(
  fetchPage: (page: number, perPage: number) => Promise<PaginatedListEnvelope<T>>,
  perPage: number = PAGINATED_MAX_PER_PAGE,
): Promise<PaginatedListEnvelope<T>> {
  const pageSize = Math.min(Math.max(1, perPage), PAGINATED_MAX_PER_PAGE);
  const first = await fetchPage(1, pageSize);

  if (first.last_page <= 1) {
    return first;
  }

  const all = [...first.data];
  for (let page = 2; page <= first.last_page; page++) {
    const next = await fetchPage(page, pageSize);
    all.push(...next.data);
  }

  const total = first.total;

  return {
    ...first,
    current_page: 1,
    data: all,
    from: total > 0 ? 1 : null,
    last_page: 1,
    last_page_url: first.first_page_url,
    links: [],
    next_page_url: null,
    per_page: total > 0 ? total : pageSize,
    prev_page_url: null,
    to: total > 0 ? total : null,
    total,
  };
}
