import { apiGetJson } from './http';
import {
  buildAcademicContext,
  type AcademicContextLoad,
  type AcademicContextPayload,
} from '../features/hierarchy/selectors/academicContextSelector';

export type { AcademicContextLoad };

interface AcademicContextResponse {
  data: AcademicContextPayload;
}

/**
 * GET /api/v1/me/academic-context — contexto académico del usuario servido
 * por el paquete compartido `maya-shared-profile-laravel` (filtrado server-side
 * por user_id, cacheado 5 min en Redis).
 *
 * Sustituye al antiguo `/api/v1/hierarchy` dms-only. El ensamblaje plana→árbol
 * está delegado al selector `buildAcademicContext` para mantener esta capa
 * `api/` limitada a HTTP.
 */
export async function fetchAcademicHierarchy(): Promise<AcademicContextLoad> {
  const body = await apiGetJson<AcademicContextResponse>('me/academic-context');
  return buildAcademicContext(body.data);
}
