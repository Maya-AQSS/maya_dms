import { beforeEach, describe, expect, it } from 'vitest';
import {
  DATA_TABLE_PAGE_SIZES,
  DEFAULT_TABLE_PAGE_SIZE,
  dropInvalidStoredPageSize,
} from './dataTablePageSize';

const STORAGE_KEY = 'maya:dms:test-table';
const PER_PAGE_KEY = `${STORAGE_KEY}:per_page`;

describe('dropInvalidStoredPageSize', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('el default es una opción válida del select', () => {
    expect(DATA_TABLE_PAGE_SIZES).toContain(DEFAULT_TABLE_PAGE_SIZE);
  });

  it('purga el 15 heredado del antiguo defaultPageSize', () => {
    localStorage.setItem(PER_PAGE_KEY, '15');
    dropInvalidStoredPageSize(STORAGE_KEY);
    expect(localStorage.getItem(PER_PAGE_KEY)).toBeNull();
  });

  it('conserva los valores que son opción del select', () => {
    for (const size of DATA_TABLE_PAGE_SIZES) {
      localStorage.setItem(PER_PAGE_KEY, String(size));
      dropInvalidStoredPageSize(STORAGE_KEY);
      expect(localStorage.getItem(PER_PAGE_KEY)).toBe(String(size));
    }
  });

  it('purga valores corruptos no numéricos', () => {
    localStorage.setItem(PER_PAGE_KEY, 'abc');
    dropInvalidStoredPageSize(STORAGE_KEY);
    expect(localStorage.getItem(PER_PAGE_KEY)).toBeNull();
  });

  it('no hace nada cuando no hay valor persistido', () => {
    expect(() => dropInvalidStoredPageSize(STORAGE_KEY)).not.toThrow();
    expect(localStorage.getItem(PER_PAGE_KEY)).toBeNull();
  });
});
