/**
 * Proceso del catálogo (FK desde templates y documents).
 *
 * Estructura jerárquica de 2 niveles:
 *   - Top-level: `parent_id === null` (códigos PE0X, PC0X, PS0X)
 *   - Sub-procesos: `parent_id` apunta al top-level (códigos PE0X.0Y, etc.)
 */
export type Process = {
  id: string;
  code: string;
  name: string;
  alias: string;
  description: string | null;
  parent_id: string | null;
};
