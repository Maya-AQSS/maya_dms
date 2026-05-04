<?php

/**
 * Catálogo reducido de usuarios mock (local / pruebas con Keycloak).
 * Campos: id, nombre, email, departamento → vista `users`: id, name, email, department.
 *
 * Nota de modelo: en catálogo corporativo el “departamento” orgánico puede modelarse solo con
 * equipos (`teams.is_department`). La columna `department` en `users` sigue existiendo en
 * migraciones/perfil por compatibilidad con FDW y búsqueda; el mock local puede rellenarla o
 * dejarla coherente con el equipo departamental cuando se alinee el producto.
 *
 * **Ids `usr_*`:** en Keycloak copia el UUID del usuario y sustituye aquí y en
 * `user_permissions_mock.php`, `templates_mock.php` (created_by / revisores),
 * `teams_mock.php`, `documents_mock.php`, `comments_mock.php` el mismo valor.
 *
 * ── Ámbito académico (BD + fallback JWT) ───────────────────────────────────
 * `users_source` no guarda ámbito. Las tablas `user_study_types` / `user_studies` /
 * `user_course_modules` (o `*_source` en local) las puebla {@see database/data/user_hierarchy_mock.php}.
 * El guard `api` fusiona ese ámbito vía {@see \App\Services\UserProfileService::getProfile()};
 * si no hay usuario en FDW o falla la consulta, se usan claims del token (fallback).
 *
 * | id (users_mock)        | Tipos de estudio en user_hierarchy_mock | Notas |
 * |------------------------|----------------------------------------|--------|
 * | ed568442… Dirección    | ST_ESPA, ST_BACH, ST_FP                  | Ver filas en user_hierarchy_mock (estudios y módulos “globales” mock) |
 * | 2ead4bf3… Secretaría  | ST_ESPA, ST_BACH, ST_FP                  | Igual |
 * | cf8bb92a… Docente ESO  | solo ST_ESPA                            | Nombre legacy; en user_hierarchy_mock es ámbito ESPA (S_ESPA) |
 * | 53bc5feb… Docente Bach | ST_BACH, ST_FP                          | user_hierarchy_mock |
 * | 50f503c6… Docente FP   | ST_FP                                   | user_hierarchy_mock |
 * | f6bbe247… Auditoría   | (ninguno)                               | Sin jerarquía en user_hierarchy_mock |
 */
return [
    [
        'id'           => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
        'nombre'       => 'Direccion Demo',
        'email'        => 'direccion.demo@maya.local',
        'departamento' => 'Dirección',
    ],
    [
        'id'           => '2ead4bf3-574c-41b4-95ca-cac7daed0664',
        'nombre'       => 'Secretaria Demo',
        'email'        => 'secretaria.demo@maya.local',
        'departamento' => 'Secretaría',
    ],
    [
        'id'           => 'cf8bb92a-0417-4a4c-918a-08dd3fd69165',
        'nombre'       => 'Docente ESO Demo',
        'email'        => 'docente.eso.demo@maya.local',
        'departamento' => 'Profesorado',
    ],
    [
        'id'           => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f',
        'nombre'       => 'Docente Bachillerato Demo',
        'email'        => 'docente.bach.demo@maya.local',
        'departamento' => 'Profesorado',
    ],
    [
        'id'           => '50f503c6-cb63-466c-852d-0b30ae130e98',
        'nombre'       => 'Docente FP Demo',
        'email'        => 'docente.fp.demo@maya.local',
        'departamento' => 'Profesorado',
    ],
    [
        'id'           => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc',
        'nombre'       => 'Auditoría Demo',
        'email'        => 'auditor.demo@maya.local',
        'departamento' => 'Auditoría',
    ],
    [
        'id'           => '848dc299-240e-4a75-9d8e-f0a04089309d',
        'nombre'       => 'Super Admin',
        'email'        => 'superadmin@maya.local',
        'departamento' => 'Administración',
    ],
];
