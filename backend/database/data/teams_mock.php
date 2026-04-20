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
            'owner_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
            'is_department' => true,
        ],
        [
            'id' => '11111111-1111-1111-1111-111111111102',
            'name' => 'Equipo académico',
            'description' => 'Equipo de prueba con docentes por jerarquía.',
            'owner_id' => '50f503c6-cb63-466c-852d-0b30ae130e98',
            'is_department' => false,
        ],
    ],
    'members' => [
        [
            'id' => '22222222-2222-2222-2222-222222222201',
            'team_id' => '11111111-1111-1111-1111-111111111101',
            'user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
            'role' => 'admin',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222202',
            'team_id' => '11111111-1111-1111-1111-111111111101',
            'user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664',
            'role' => 'member',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222203',
            'team_id' => '11111111-1111-1111-1111-111111111102',
            'user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98',
            'role' => 'admin',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222204',
            'team_id' => '11111111-1111-1111-1111-111111111102',
            'user_id' => 'cf8bb92a-0417-4a4c-918a-08dd3fd69165',
            'role' => 'member',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222205',
            'team_id' => '11111111-1111-1111-1111-111111111102',
            'user_id' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f',
            'role' => 'member',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222206',
            'team_id' => '11111111-1111-1111-1111-111111111102',
            'user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664',
            'role' => 'member',
        ],
    ],
];
