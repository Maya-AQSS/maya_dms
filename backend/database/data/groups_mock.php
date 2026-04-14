<?php

/**
 * Datos mock de grupos y membresías para local/testing.
 * Los user_id deben coincidir con database/data/users_mock.php.
 * Cada membresía incluye id UUID (PK) para inserts directos en group_members.
 */
return [
    'groups' => [
        [
            'id' => '11111111-1111-1111-1111-111111111101',
            'name' => 'Grupo dirección',
            'description' => 'Grupo de prueba con owner de dirección.',
            'owner_id' => 'usr_direction_demo',
        ],
        [
            'id' => '11111111-1111-1111-1111-111111111102',
            'name' => 'Grupo académico',
            'description' => 'Grupo de prueba con docentes por jerarquía.',
            'owner_id' => 'usr_hierarchy_fp_demo',
        ],
    ],
    'members' => [
        [
            'id' => '22222222-2222-2222-2222-222222222201',
            'group_id' => '11111111-1111-1111-1111-111111111101',
            'user_id' => 'usr_direction_demo',
            'role' => 'admin',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222202',
            'group_id' => '11111111-1111-1111-1111-111111111101',
            'user_id' => 'usr_secretariat_demo',
            'role' => 'member',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222203',
            'group_id' => '11111111-1111-1111-1111-111111111102',
            'user_id' => 'usr_hierarchy_fp_demo',
            'role' => 'admin',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222204',
            'group_id' => '11111111-1111-1111-1111-111111111102',
            'user_id' => 'usr_hierarchy_eso_demo',
            'role' => 'member',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222205',
            'group_id' => '11111111-1111-1111-1111-111111111102',
            'user_id' => 'usr_hierarchy_bach_demo',
            'role' => 'member',
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222206',
            'group_id' => '11111111-1111-1111-1111-111111111102',
            'user_id' => 'usr_secretariat_demo',
            'role' => 'member',
        ],
    ],
];
