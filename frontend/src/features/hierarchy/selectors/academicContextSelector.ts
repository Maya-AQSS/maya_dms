import type { AcademicHierarchy } from '../../../types/hierarchy';
import type { UserTeam } from '../../../api/users';

/**
 * Shapes de la respuesta plana del endpoint compartido
 * `GET /api/v1/me/academic-context`. Idéntica a `AcademicContext` del
 * paquete shared-profile-react pero mantenida aquí localmente para no
 * acoplar el selector a la versión del paquete.
 */
interface AcademicContextItem {
  id: string;
  code: string;
  name: string;
}

export interface AcademicContextStudy extends AcademicContextItem {
  study_type_id: string;
}

export interface AcademicContextModule extends AcademicContextItem {
  study_id: string;
}

export interface AcademicContextTeam extends AcademicContextItem {
  is_department: boolean;
}

export interface AcademicContextPayload {
  study_types: AcademicContextItem[];
  studies: AcademicContextStudy[];
  modules: AcademicContextModule[];
  teams: AcademicContextTeam[];
  _status: Record<string, string>;
}

export interface AcademicContextLoad {
  hierarchy: AcademicHierarchy;
  teams: UserTeam[];
}

/**
 * Ensambla el árbol jerárquico (study_types → studies → course_modules) y la
 * lista de equipos a partir de las listas planas devueltas por el endpoint.
 *
 * Aislado en un selector porque:
 * - Permite testear la transformación sin tocar HTTP.
 * - Mantiene la capa `api/` limitada a fetch + parse JSON.
 * - Habilita reutilización futura desde otros consumidores (admin views).
 */
export function buildAcademicContext(payload: AcademicContextPayload): AcademicContextLoad {
  const studyTypes = payload.study_types ?? [];
  const studies = payload.studies ?? [];
  const modules = payload.modules ?? [];
  const teamsPayload = payload.teams ?? [];

  const modulesByStudy = new Map<string, AcademicContextModule[]>();
  for (const mod of modules) {
    const bucket = modulesByStudy.get(mod.study_id);
    if (bucket) {
      bucket.push(mod);
    } else {
      modulesByStudy.set(mod.study_id, [mod]);
    }
  }

  const studiesByType = new Map<string, AcademicContextStudy[]>();
  for (const study of studies) {
    const bucket = studiesByType.get(study.study_type_id);
    if (bucket) {
      bucket.push(study);
    } else {
      studiesByType.set(study.study_type_id, [study]);
    }
  }

  const hierarchy: AcademicHierarchy = studyTypes.map((type) => ({
    id: type.id,
    name: type.name,
    studies: (studiesByType.get(type.id) ?? []).map((study) => ({
      id: study.id,
      study_type_id: study.study_type_id,
      name: study.name,
      course_modules: (modulesByStudy.get(study.id) ?? []).map((mod) => ({
        id: mod.id,
        study_id: mod.study_id,
        name: mod.name,
      })),
    })),
  }));

  const teams: UserTeam[] = teamsPayload.map((team) => ({
    id: team.id,
    name: team.name,
    description: null,
    is_department: team.is_department,
  }));

  return { hierarchy, teams };
}
