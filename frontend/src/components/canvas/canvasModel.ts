/**
 * Modelo genérico de un lienzo de posicionamiento absoluto en milímetros.
 *
 * Extraído de `ThemeGridEditor` para poder reutilizar la mecánica (página en
 * mm, drag/move/resize/snap/clamp, capas) en otros editores — p. ej. el futuro
 * bloque "portada". Es deliberadamente estructural: `ThemeLayoutRegion` es
 * asignable a `CanvasRegion` sin conversión.
 */

export interface CanvasBox {
  /** Offset desde la esquina superior-izquierda de la página, en mm. */
  x: number;
  y: number;
  /** Tamaño en mm. */
  w: number;
  h: number;
  /** Capa (mayor = encima). */
  z?: number;
}

export interface CanvasRegion {
  id: string;
  box?: CanvasBox;
  type?: string;
  props?: Record<string, unknown>;
}

export type BoxPatch = Partial<CanvasBox>;

export interface PageMm {
  width: number;
  height: number;
}

/**
 * Escala fija del lienzo para ser WYSIWYG con el PDF: 96 dpi → 1 mm =
 * 96/25.4 px ≈ 3.78 px (A4 = 210 mm ≈ 794 px, igual que el PDF a 96 dpi).
 */
export const PX_PER_MM = 96 / 25.4;
export const SNAP_MM = 1; // imán de posicionamiento (mm)
export const MIN_SIZE_MM = 5; // tamaño mínimo de un bloque (mm)

export const snap = (v: number): number => Math.round(v / SNAP_MM) * SNAP_MM;
export const clamp = (v: number, min: number, max: number): number =>
  Math.min(max, Math.max(min, v));
