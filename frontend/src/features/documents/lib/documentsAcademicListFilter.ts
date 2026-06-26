import type { AcademicHierarchy } from '../../../types/hierarchy';
import type { MeProfile } from '../../../types/users';
import type { CascadeDocumentFilters } from '../types';

export const DOCUMENTS_ACADEMIC_CLEARED_KEY = 'maya:dms:documents-academic-cleared';

export type ProfileAcademicScope = {
  studyTypeIds: string[];
  studyIds: string[];
  moduleIds: string[];
};

export type DocumentsAcademicUrlPatch = {
  study_type_id: string;
  study_id: string;
  module_id: string;
  profile_academic_default: string;
};

export function profileToAcademicScope(
  profile: Pick<MeProfile, 'study_type_ids' | 'study_ids' | 'module_ids'>,
): ProfileAcademicScope {
  return {
    studyTypeIds: profile.study_type_ids ?? [],
    studyIds: profile.study_ids ?? [],
    moduleIds: profile.module_ids ?? [],
  };
}

export function countProfileAcademicScopes(scope: ProfileAcademicScope): number {
  return scope.studyTypeIds.length + scope.studyIds.length + scope.moduleIds.length;
}

export function findModuleCascade(
  hierarchy: AcademicHierarchy,
  moduleId: string,
): CascadeDocumentFilters | null {
  for (const type of hierarchy) {
    for (const study of type.studies) {
      for (const mod of study.course_modules) {
        if (mod.id === moduleId) {
          return { studyTypeId: type.id, studyId: study.id, moduleId: mod.id };
        }
      }
    }
  }
  return null;
}

export function findStudyCascade(
  hierarchy: AcademicHierarchy,
  studyId: string,
): CascadeDocumentFilters | null {
  for (const type of hierarchy) {
    for (const study of type.studies) {
      if (study.id === studyId) {
        return { studyTypeId: type.id, studyId: study.id, moduleId: '' };
      }
    }
  }
  return null;
}

/**
 * Resuelve el estado inicial de filtros académicos en URL a partir del perfil.
 * Un solo scope → cascada (singulares); varios → `profile_academic_default=1`.
 */
export function resolveInitialAcademicUrlPatch(
  profile: Pick<MeProfile, 'study_type_ids' | 'study_ids' | 'module_ids'>,
  hierarchy: AcademicHierarchy,
): DocumentsAcademicUrlPatch | null {
  const scope = profileToAcademicScope(profile);
  const total = countProfileAcademicScopes(scope);
  if (total === 0) {
    return null;
  }

  const empty: DocumentsAcademicUrlPatch = {
    study_type_id: '',
    study_id: '',
    module_id: '',
    profile_academic_default: '',
  };

  if (total === 1) {
    if (scope.moduleIds.length === 1) {
      const cascade = findModuleCascade(hierarchy, scope.moduleIds[0]);
      if (cascade) {
        return {
          ...empty,
          study_type_id: cascade.studyTypeId,
          study_id: cascade.studyId,
          module_id: cascade.moduleId,
        };
      }
      return { ...empty, module_id: scope.moduleIds[0] };
    }
    if (scope.studyIds.length === 1) {
      const cascade = findStudyCascade(hierarchy, scope.studyIds[0]);
      if (cascade) {
        return {
          ...empty,
          study_type_id: cascade.studyTypeId,
          study_id: cascade.studyId,
        };
      }
      return { ...empty, study_id: scope.studyIds[0] };
    }
    if (scope.studyTypeIds.length === 1) {
      return { ...empty, study_type_id: scope.studyTypeIds[0] };
    }
  }

  return { ...empty, profile_academic_default: '1' };
}

export function hasExplicitAcademicUrlFilters(filters: {
  study_type_id?: string;
  study_id?: string;
  module_id?: string;
  profile_academic_default?: string;
}): boolean {
  return !!(
    filters.study_type_id ||
    filters.study_id ||
    filters.module_id ||
    filters.profile_academic_default
  );
}

export function isAcademicFilterClearedInSession(): boolean {
  try {
    return sessionStorage.getItem(DOCUMENTS_ACADEMIC_CLEARED_KEY) === '1';
  } catch {
    return false;
  }
}

export function markAcademicFilterClearedInSession(): void {
  try {
    sessionStorage.setItem(DOCUMENTS_ACADEMIC_CLEARED_KEY, '1');
  } catch {
    /* noop */
  }
}

export function clearAcademicFilterClearedInSession(): void {
  try {
    sessionStorage.removeItem(DOCUMENTS_ACADEMIC_CLEARED_KEY);
  } catch {
    /* noop */
  }
}

/** Etiquetas legibles del contexto académico del perfil (para indicador UI). */
export function formatProfileAcademicScopeLabels(
  profile: Pick<MeProfile, 'study_type_ids' | 'study_ids' | 'module_ids'>,
  hierarchy: AcademicHierarchy,
): string[] {
  const scope = profileToAcademicScope(profile);
  const labels: string[] = [];

  for (const typeId of scope.studyTypeIds) {
    const type = hierarchy.find((t) => t.id === typeId);
    labels.push(type?.name ?? typeId);
  }
  for (const studyId of scope.studyIds) {
    let name: string | null = null;
    for (const type of hierarchy) {
      const study = type.studies.find((s) => s.id === studyId);
      if (study) {
        name = study.name;
        break;
      }
    }
    labels.push(name ?? studyId);
  }
  for (const moduleId of scope.moduleIds) {
    let name: string | null = null;
    for (const type of hierarchy) {
      for (const study of type.studies) {
        const mod = study.course_modules.find((m) => m.id === moduleId);
        if (mod) {
          name = mod.name;
          break;
        }
      }
      if (name) break;
    }
    labels.push(name ?? moduleId);
  }

  return labels;
}

export function formatCascadeFilterLabels(
  filters: { studyTypeId: string; studyId: string; moduleId: string },
  hierarchy: AcademicHierarchy,
): string[] {
  const labels: string[] = [];
  if (filters.studyTypeId) {
    labels.push(hierarchy.find((t) => t.id === filters.studyTypeId)?.name ?? filters.studyTypeId);
  }
  if (filters.studyId) {
    let name: string | null = null;
    for (const type of hierarchy) {
      const study = type.studies.find((s) => s.id === filters.studyId);
      if (study) {
        name = study.name;
        break;
      }
    }
    labels.push(name ?? filters.studyId);
  }
  if (filters.moduleId) {
    let name: string | null = null;
    for (const type of hierarchy) {
      for (const study of type.studies) {
        const mod = study.course_modules.find((m) => m.id === filters.moduleId);
        if (mod) {
          name = mod.name;
          break;
        }
      }
      if (name) break;
    }
    labels.push(name ?? filters.moduleId);
  }
  return labels;
}

export function hasActiveAcademicListFilter(filters: {
  study_type_id?: string;
  study_id?: string;
  module_id?: string;
  profile_academic_default?: string;
}): boolean {
  return hasExplicitAcademicUrlFilters(filters);
}
