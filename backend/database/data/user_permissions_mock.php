<?php

/**
 * Asignaciones usuario ↔ permiso para mocks locales/testing.
 * `user_id` debe coincidir con `id` en {@see database/data/users_mock.php}.
 *
 * Idempotente: {@see \Database\Seeders\UserPermissionsSeeder} usa insertOrIgnore.
 */
return [
    // Dirección: CRUD plantillas y documentos + auditoría + búsqueda de usuarios
    ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'permission_code' => 'templates.create'],
    ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'permission_code' => 'templates.read'],
    ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'permission_code' => 'templates.update'],
    ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'permission_code' => 'templates.delete'],
    ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'permission_code' => 'documents.create'],
    ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'permission_code' => 'documents.read'],
    ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'permission_code' => 'documents.update'],
    ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'permission_code' => 'documents.delete'],
    ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'permission_code' => 'audit.read'],
    ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'permission_code' => 'users.search'],
    ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'permission_code' => 'templates.review'],
    ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'permission_code' => 'documents.review'],

    // Secretaría: mismo conjunto amplio que Dirección en mock (catálogo global + CRUD)
    ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'permission_code' => 'templates.create'],
    ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'permission_code' => 'templates.read'],
    ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'permission_code' => 'templates.update'],
    ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'permission_code' => 'templates.delete'],
    ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'permission_code' => 'templates.review'],
    ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'permission_code' => 'documents.create'],
    ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'permission_code' => 'documents.read'],
    ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'permission_code' => 'documents.update'],
    ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'permission_code' => 'documents.delete'],
    ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'permission_code' => 'documents.review'],
    ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'permission_code' => 'users.search'],
    ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'permission_code' => 'audit.read'],

    // Docentes por etapa: solo lectura catálogo que su JWT permita ver
    ['user_id' => 'cf8bb92a-0417-4a4c-918a-08dd3fd69165', 'permission_code' => 'templates.read'],
    ['user_id' => 'cf8bb92a-0417-4a4c-918a-08dd3fd69165', 'permission_code' => 'documents.read'],
    ['user_id' => 'cf8bb92a-0417-4a4c-918a-08dd3fd69165', 'permission_code' => 'documents.create'],
    ['user_id' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f', 'permission_code' => 'templates.read'],
    ['user_id' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f', 'permission_code' => 'documents.read'],
    ['user_id' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f', 'permission_code' => 'documents.create'],
    ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'permission_code' => 'templates.read'],
    ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'permission_code' => 'documents.read'],
    ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'permission_code' => 'documents.create'],
    ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'permission_code' => 'templates.create'],
    ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'permission_code' => 'templates.update'],

    // Auditoría: permisos completos en mock (sin jerarquía académica en user_hierarchy)
    ['user_id' => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc', 'permission_code' => 'templates.create'],
    ['user_id' => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc', 'permission_code' => 'templates.read'],
    ['user_id' => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc', 'permission_code' => 'templates.update'],
    ['user_id' => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc', 'permission_code' => 'templates.delete'],
    ['user_id' => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc', 'permission_code' => 'templates.review'],
    ['user_id' => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc', 'permission_code' => 'documents.create'],
    ['user_id' => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc', 'permission_code' => 'documents.read'],
    ['user_id' => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc', 'permission_code' => 'documents.update'],
    ['user_id' => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc', 'permission_code' => 'documents.delete'],
    ['user_id' => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc', 'permission_code' => 'documents.review'],
    ['user_id' => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc', 'permission_code' => 'users.search'],
    ['user_id' => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc', 'permission_code' => 'audit.read'],
    
    // Super Admin: permiso admin para ver todo
    ['user_id' => '848dc299-240e-4a75-9d8e-f0a04089309d', 'permission_code' => 'admin'],
];
