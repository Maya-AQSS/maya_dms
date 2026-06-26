import { describe, expect, it } from 'vitest';
import type { AcademicHierarchy } from '../../../../types/hierarchy';
import {
  countProfileAcademicScopes,
  profileToAcademicScope,
  resolveInitialAcademicUrlPatch,
} from '../documentsAcademicListFilter';

const hierarchy: AcademicHierarchy = [
  {
    id: 'type-1',
    name: 'Grado',
    studies: [
      {
        id: 'study-1',
        study_type_id: 'type-1',
        name: 'Informática',
        course_modules: [
          { id: 'mod-1', study_id: 'study-1', name: 'Módulo A' },
          { id: 'mod-2', study_id: 'study-1', name: 'Módulo B' },
        ],
      },
    ],
  },
];

describe('documentsAcademicListFilter', () => {
  it('cuenta scopes del perfil', () => {
    const scope = profileToAcademicScope({
      study_type_ids: ['t1'],
      study_ids: ['s1', 's2'],
      module_ids: [],
    });
    expect(countProfileAcademicScopes(scope)).toBe(3);
  });

  it('un solo módulo → cascada completa en URL', () => {
    const patch = resolveInitialAcademicUrlPatch(
      { study_type_ids: [], study_ids: [], module_ids: ['mod-1'] },
      hierarchy,
    );
    expect(patch).toEqual({
      study_type_id: 'type-1',
      study_id: 'study-1',
      module_id: 'mod-1',
      profile_academic_default: '',
    });
  });

  it('un solo estudio → cascada tipo+estudio', () => {
    const patch = resolveInitialAcademicUrlPatch(
      { study_type_ids: [], study_ids: ['study-1'], module_ids: [] },
      hierarchy,
    );
    expect(patch).toEqual({
      study_type_id: 'type-1',
      study_id: 'study-1',
      module_id: '',
      profile_academic_default: '',
    });
  });

  it('varios scopes → profile_academic_default', () => {
    const patch = resolveInitialAcademicUrlPatch(
      { study_type_ids: [], study_ids: ['study-1'], module_ids: ['mod-2'] },
      hierarchy,
    );
    expect(patch).toEqual({
      study_type_id: '',
      study_id: '',
      module_id: '',
      profile_academic_default: '1',
    });
  });

  it('sin contexto académico → null', () => {
    expect(
      resolveInitialAcademicUrlPatch(
        { study_type_ids: [], study_ids: [], module_ids: [] },
        hierarchy,
      ),
    ).toBeNull();
  });
});
