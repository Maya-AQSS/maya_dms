<?php

/**
 * Catálogo de permisos mock (clave primaria = `code`).
 * 
 * Idempotente: {@see \Database\Seeders\PermissionsSeeder} usa insertOrIgnore.
 */
return [
    ['code' => 'templates.create', 'name' => 'Plantillas — crear', 'description' => 'Crear plantillas (alcance lo define la policy).'],
    ['code' => 'templates.read', 'name' => 'Plantillas — leer', 'description' => 'Listar y ver plantillas visibles.'],
    ['code' => 'templates.update', 'name' => 'Plantillas — actualizar', 'description' => 'Editar plantillas permitidas.'],
    ['code' => 'templates.delete', 'name' => 'Plantillas — eliminar', 'description' => 'Eliminar o archivar plantillas permitidas.'],

    ['code' => 'documents.create', 'name' => 'Documentos — crear', 'description' => 'Crear documentos.'],
    ['code' => 'documents.read', 'name' => 'Documentos — leer', 'description' => 'Ver documentos visibles.'],
    ['code' => 'documents.update', 'name' => 'Documentos — actualizar', 'description' => 'Editar documentos.'],
    ['code' => 'documents.delete', 'name' => 'Documentos — eliminar', 'description' => 'Eliminar documentos.'],

    ['code' => 'audit.read', 'name' => 'Auditoría — leer', 'description' => 'Listar historial de auditoría de un recurso sin entrar por el alcance habitual del modelo (asignación en BD). En mocks de demo suele darse solo a usuarios de Dirección para pruebas.'],
];
