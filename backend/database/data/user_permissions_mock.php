<?php

/**
 * Asignaciones usuario ↔ permiso para mocks locales/testing.
 * `user_id` debe coincidir con `id` en {@see database/data/users_mock.php}.
 *
 * Idempotente: {@see \Database\Seeders\UserPermissionsSeeder} usa insertOrIgnore.
 */
return [
    // Dirección: CRUD plantillas y documentos (equipos sin permisos CRUD en catálogo)
    ['user_id' => 'usr_direction_demo', 'permission_code' => 'templates.create'],
    ['user_id' => 'usr_direction_demo', 'permission_code' => 'templates.read'],
    ['user_id' => 'usr_direction_demo', 'permission_code' => 'templates.update'],
    ['user_id' => 'usr_direction_demo', 'permission_code' => 'templates.delete'],
    ['user_id' => 'usr_direction_demo', 'permission_code' => 'documents.create'],
    ['user_id' => 'usr_direction_demo', 'permission_code' => 'documents.read'],
    ['user_id' => 'usr_direction_demo', 'permission_code' => 'documents.update'],
    ['user_id' => 'usr_direction_demo', 'permission_code' => 'documents.delete'],
    ['user_id' => 'usr_direction_demo', 'permission_code' => 'audit.read'],
    ['user_id' => 'usr_javier_navarro', 'permission_code' => 'audit.read'],

    ['user_id' => 'usr_secretariat_demo', 'permission_code' => 'templates.read'],
    ['user_id' => 'usr_secretariat_demo', 'permission_code' => 'documents.read'],
    ['user_id' => 'usr_secretariat_demo', 'permission_code' => 'documents.create'],
    ['user_id' => 'usr_secretariat_demo', 'permission_code' => 'documents.update'],

    ['user_id' => 'usr_hierarchy_eso_demo', 'permission_code' => 'templates.read'],
    ['user_id' => 'usr_hierarchy_eso_demo', 'permission_code' => 'documents.read'],

    ['user_id' => 'usr_hierarchy_bach_demo', 'permission_code' => 'templates.read'],
    ['user_id' => 'usr_hierarchy_bach_demo', 'permission_code' => 'documents.read'],

    ['user_id' => 'usr_hierarchy_fp_demo', 'permission_code' => 'templates.read'],
    ['user_id' => 'usr_hierarchy_fp_demo', 'permission_code' => 'documents.read'],

    ['user_id' => 'usr_ana_martinez', 'permission_code' => 'templates.read'],
    ['user_id' => 'usr_maria_garcia', 'permission_code' => 'templates.read'],
    ['user_id' => 'usr_juan_rodriguez', 'permission_code' => 'templates.read'],
    ['user_id' => 'usr_juan_rodriguez', 'permission_code' => 'documents.read'],
];
