import { describe, expect, it } from 'vitest';
import { normalizePaginatedResponse } from './paginatedList';

describe('normalizePaginatedResponse', () => {
  it('normaliza el envelope plano de Laravel (current_page/total en la raíz)', () => {
    const result = normalizePaginatedResponse<{ id: number }>({
      current_page: 2,
      data: [{ id: 1 }],
      last_page: 3,
      per_page: 10,
      total: 25,
    });

    expect(result.current_page).toBe(2);
    expect(result.last_page).toBe(3);
    expect(result.per_page).toBe(10);
    expect(result.total).toBe(25);
    expect(result.data).toEqual([{ id: 1 }]);
  });

  it('normaliza el envelope con paginación anidada bajo `meta` (p. ej. processes)', () => {
    const result = normalizePaginatedResponse<{ id: number }>({
      data: [{ id: 1 }, { id: 2 }],
      meta: { current_page: 1, last_page: 2, per_page: 10, total: 16 },
    });

    expect(result.current_page).toBe(1);
    expect(result.last_page).toBe(2);
    expect(result.per_page).toBe(10);
    expect(result.total).toBe(16);
    expect(result.data).toHaveLength(2);
  });

  it('prioriza los campos de la raíz cuando coexisten con `meta`', () => {
    const result = normalizePaginatedResponse<{ id: number }>({
      data: [{ id: 1 }],
      total: 50,
      meta: { total: 5 },
    });

    expect(result.total).toBe(50);
  });

  it('trata un array pelado como página única', () => {
    const result = normalizePaginatedResponse<{ id: number }>([{ id: 1 }, { id: 2 }]);

    expect(result.current_page).toBe(1);
    expect(result.last_page).toBe(1);
    expect(result.total).toBe(2);
    expect(result.data).toHaveLength(2);
  });

  it('cae a defaults seguros (last_page=1, total=data.length) sin metadatos', () => {
    const result = normalizePaginatedResponse<{ id: number }>({ data: [{ id: 1 }] });

    expect(result.current_page).toBe(1);
    expect(result.last_page).toBe(1);
    expect(result.total).toBe(1);
  });
});
