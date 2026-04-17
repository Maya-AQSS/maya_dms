<?php

/**
 * Datos mock de equipos y membresías para local/testing.
 * Los user_id deben coincidir con database/data/users_mock.php.
 * Cada membresía incluye id UUID (PK) para inserts directos en team_members.
 *
 * Los equipos se insertan en `teams_source` (local con FDW) o en la tabla `teams` (entorno testing),
 * no en la vista `teams` (solo lectura, catálogo externo vía FDW en no-testing).
 */
return [
    'teams' => [
        [
            'id' => '11111111-1111-1111-1111-111111111101',
            'name' => 'Equipo dirección',
            'description' => 'Equipo de prueba con owner de dirección.',
            'owner_id' => 'usr_direction_demo',
            'is_department' => true,
        ],
        [
            'id' => '11111111-1111-1111-1111-111111111102',
            'name' => 'Equipo académico',
            'description' => 'Equipo de prueba con docentes por jerarquía.',
            'owner_id' => 'usr_hierarchy_fp_demo',
            'is_department' => false,
        ],
    ],
    'members' => [
        [
            'id' => '22222222-2222-2222-2222-222222222201',
            'team_id' => '11111111-1111-1111-1111-111111111101',
            'user_id' => 'usr_direction_demo',
            'role' => 'admin',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222202',
            'team_id' => '11111111-1111-1111-1111-111111111101',
            'user_id' => 'usr_secretariat_demo',
            'role' => 'member',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222203',
            'team_id' => '11111111-1111-1111-1111-111111111102',
            'user_id' => 'usr_hierarchy_fp_demo',
            'role' => 'admin',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222204',
            'team_id' => '11111111-1111-1111-1111-111111111102',
            'user_id' => 'usr_hierarchy_eso_demo',
            'role' => 'member',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222205',
            'team_id' => '11111111-1111-1111-1111-111111111102',
            'user_id' => 'usr_hierarchy_bach_demo',
            'role' => 'member',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222206',
            'team_id' => '11111111-1111-1111-1111-111111111102',
            'user_id' => 'usr_secretariat_demo',
            'role' => 'member',
        ],
    ],
];
