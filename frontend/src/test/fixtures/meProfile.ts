import type { MeProfile } from '../../types/users';

/**
 * Perfil mínimo válido para tests (misma forma que GET /api/v1/me → data).
 */
export function meProfileFixture(overrides: Partial<MeProfile> = {}): MeProfile {
  return {
    id: 'usr_test_fixture',
    email: 'fixture@test.local',
    name: 'Usuario Demo',
    locale: 'es',
    study_type_ids: [],
    study_ids: [],
    module_ids: [],
    team_ids: [],
    permissions: [
      'template.index',
      'template.show',
      'template.create',
      'template.update',
      'template.delete',
      'documents.create',
      'documents.read',
      'documents.update',
    ],
    source: 'fdw',
    ...overrides,
  };
}
