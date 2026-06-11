/** Opciones del select "Por página" de DataTable/Pagination (shared-ui-react). */
export const DATA_TABLE_PAGE_SIZES: readonly number[] = [10, 25, 50, 75, 100];

/** Tamaño de página por defecto de los listados server-side (debe ser opción del select). */
export const DEFAULT_TABLE_PAGE_SIZE = 10;

/**
 * Purga el `per_page` persistido en localStorage cuando no es una opción válida
 * del select (p. ej. el `15` heredado del antiguo defaultPageSize): un valor
 * fuera de la lista hace que el select muestre la primera opción ("10") mientras
 * la request sigue enviando otro tamaño. Llamar ANTES de `useServerTable`.
 */
export function dropInvalidStoredPageSize(storageKey: string): void {
  try {
    const raw = localStorage.getItem(`${storageKey}:per_page`);
    if (raw !== null && !DATA_TABLE_PAGE_SIZES.includes(Number(raw))) {
      localStorage.removeItem(`${storageKey}:per_page`);
    }
  } catch {
    /* localStorage no disponible — nada que sanear */
  }
}
