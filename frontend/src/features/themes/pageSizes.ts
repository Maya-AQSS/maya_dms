/**
 * Dimensiones físicas de página en milímetros, compartidas por el editor de
 * layout (canvas absoluto mm) y la previsualización en miniatura.
 *
 * El modelo de posición de los bloques del theme es absoluto en mm relativo a
 * la esquina superior-izquierda de la página (campo `box` de cada region). El
 * backend (Blade) usa exactamente las mismas dimensiones para traducir mm→cm
 * al generar el PDF — esta tabla es la única fuente de verdad del tamaño físico.
 */

export interface PageDimsMm {
  /** Ancho de página en mm (orientación vertical). */
  width: number;
  /** Alto de página en mm. */
  height: number;
}

/** Tamaños soportados. Valores ISO/ANSI en mm, orientación vertical. */
export const PAGE_SIZES_MM: Record<string, PageDimsMm> = {
  A4: { width: 210, height: 297 },
  Letter: { width: 215.9, height: 279.4 },
  A3: { width: 297, height: 420 },
};

/** Tamaño por defecto cuando el theme no especifica uno conocido. */
export const DEFAULT_PAGE_DIMS: PageDimsMm = PAGE_SIZES_MM.A4;

/** Devuelve las dimensiones en mm para un identificador de tamaño de página. */
export function pageDimsMm(size?: string | null): PageDimsMm {
  if (size && PAGE_SIZES_MM[size]) return PAGE_SIZES_MM[size];
  return DEFAULT_PAGE_DIMS;
}
