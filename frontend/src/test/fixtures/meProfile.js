/**
 * Perfil mínimo válido para tests (misma forma que GET /api/v1/me → data).
 */
export function meProfileFixture(overrides = {}) {
    return {
        id: 'usr_test_fixture',
        email: 'fixture@test.local',
        name: 'Usuario Demo',
        department: 'QA',
        study_type_ids: [],
        study_ids: [],
        module_ids: [],
        team_ids: [],
        permissions: [
            'templates.read',
            'templates.create',
            'templates.update',
            'templates.delete',
            'documents.create',
            'documents.read',
            'documents.update',
            'users.search',
        ],
        teams: [],
        source: 'fdw',
        ...overrides,
    };
}
