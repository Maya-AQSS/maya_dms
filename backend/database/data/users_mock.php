<?php

/**
 * Catálogo reducido de usuarios mock (local / pruebas con Keycloak).
 * Campos: id, nombre, email, departamento → vista `users`: id, name, email, department.
 *
 * **Ids `usr_*`:** en Keycloak copia el UUID del usuario y sustituye aquí y en
 * `user_permissions_mock.php`, `templates_mock.php` (created_by / revisores),
 * `teams_mock.php`, `documents_mock.php`, `comments_mock.php` el mismo valor.
 *
 * ── Ámbito académico (JWT / Keycloak) ─────────────────────────────────────
 * `users_source` no tiene columnas de ámbito; {@see \App\Services\UserProfileService}
 * rellena `study_type_ids`, `study_ids`, `module_ids` desde el token.
 *
 * | id lógico              | study_type_ids | study_ids | module_ids |
 * |------------------------|----------------|-----------|------------|
 * | usr_direction_demo     | ST_ESO, ST_BACH, ST_FP | (todos los studies del mock de jerarquía) | (todos los módulos del mock) |
 * | usr_secretariat_demo   | ST_ESPA, ST_BACH, ST_FP | (todos los studies del mock) | (todos los módulos del mock) |
 * | usr_hierarchy_eso_demo | ST_ESO | S_ESO_1, S_ESO_2 | M_MAT_1, M_ENG_1, M_LEN_2 |
 * | usr_hierarchy_bach_demo| ST_BACH | S_BACH_1_C, S_BACH_2_C | M_FIS_1C, M_BIO_2C |
 * | usr_hierarchy_fp_demo  | ST_FP | S_FP_DAW, S_FP_ASIR | M_DAW_DWECL, M_DAW_DWES, M_ASIR_SRI |
 * | usr_auditor_demo       | (ninguno en user_hierarchy mock) | (ninguno) | (ninguno) |
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
        'departamento' => 'ST_ESO',
    ],
    [
        'id'           => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f',
        'nombre'       => 'Docente Bachillerato Demo',
        'email'        => 'docente.bach.demo@maya.local',
        'departamento' => 'ST_BACH',
    ],
    [
        'id'           => '50f503c6-cb63-466c-852d-0b30ae130e98',
        'nombre'       => 'Docente FP Demo',
        'email'        => 'docente.fp.demo@maya.local',
        'departamento' => 'ST_FP',
    ],
    [
        'id'           => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc',
        'nombre'       => 'Auditoría Demo',
        'email'        => 'auditor.demo@maya.local',
        'departamento' => 'Auditoría',
    ],
];
