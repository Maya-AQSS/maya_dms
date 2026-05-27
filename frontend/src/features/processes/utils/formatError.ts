import { ApiHttpError } from '../../../api/http';

export function formatError(err: unknown): string {
  if (err instanceof ApiHttpError) {
    if (err.status === 404) return err.message || 'Proceso no encontrado.';
    if (err.status === 409) return err.message || 'El proceso tiene dependientes y no puede eliminarse.';
    if (err.status === 422) return err.message || 'Datos no válidos.';
    if (err.status === 403) return err.message || 'Sin permiso para esta acción.';
    return err.message || `Error HTTP ${err.status}`;
  }
  return err instanceof Error ? err.message : 'Error desconocido';
}
