/**
 * Proceso del catálogo (FK desde templates y documents).
 *
 * Estructura jerárquica de 2 niveles:
 *   - Top-level: `process_parent_id === null` (códigos PE0X, PC0X, PS0X)
 *   - Sub-procesos: `process_parent_id` apunta al proceso padre (códigos PE0X.0Y, etc.)
 */
export type Process = {
  id: string;
  code: string;
  name: string;
  /** Texto user-facing (≤25 chars) que se muestra en sidebar y listados compactos. */
  alias: string;
  /** Slug de icono resuelto por `processIcons.tsx` (lucide-style). */
  icon: string | null;
  /** Hex `#RRGGBB` único por proceso para distinguirlos visualmente. */
  color: string | null;
  description: string | null;
  process_parent_id: string | null;
};

/** Conteo de dependientes afectados al eliminar un proceso. */
export type ProcessDeletionPreview = {
  templates_count: number;
  documents_count: number;
  subprocess_count: number;
};
