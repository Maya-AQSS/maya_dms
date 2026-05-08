<?php

/**
 * Catálogo reducido de usuarios mock (local / pruebas con Keycloak).
 * Campos: id, nombre, email, departamento (metadato mock; la vista FDW `users` no expone departamento).
 *
 * **Ids `usr_*`:** en Keycloak copia el UUID del usuario y sustituye aquí y en
 * `user_permissions_mock.php`, `templates_mock.php` (created_by / revisores),
 * `teams_mock.php`, `documents_mock.php`, `comments_mock.php` el mismo valor.
 *
 * ── Ámbito académico ───────────────────────────────────────────────────────
 * `users_source` no tiene columnas de ámbito; {@see \App\Services\UserProfileService}
 * las obtiene desde las tablas pivote (seed) y, si no hay filas, desde claims JWT.
 *
 * | id lógico               | study_type_ids        | study_ids                         | module_ids                                    |
 * |-------------------------|-----------------------|-----------------------------------|-----------------------------------------------|
 * | usr_direction_demo      | ST_ESPA, ST_BACH, ST_FP | (todos los studies del mock)    | (todos los módulos del mock)                  |
 * | usr_secretariat_demo    | ST_ESPA, ST_BACH, ST_FP | (todos los studies del mock)    | (todos los módulos del mock)                  |
 * | usr_espa_demo           | ST_ESPA               | S_ESPA                            | M_MAT_1, M_ENG_1, M_LEN_2                    |
 * | usr_bach_demo           | ST_BACH, ST_FP        | S_BACH_1_C, S_BACH_2_C, S_FP_SMR_1 | M_FIS_1C, M_BIO_2C, M_SMR_MME             |
 * | usr_fp_demo             | ST_FP                 | S_FP_DAW, S_FP_ASIR               | M_DAW_DWECL, M_DAW_DWES, M_DAW_DIW, M_ASIR_SRI, M_ASIR_SAD, M_SMR_MME, M_SMR_PAR |
 * | usr_auditor_demo        | (ninguno)             | (ninguno)                         | (ninguno) → muestra jerarquía completa        |
 * | usr_superadmin          | (ninguno)             | (ninguno)                         | (ninguno) → muestra jerarquía completa        |
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
        'nombre'       => 'Docente ESPA Demo',
        'email'        => 'docente.espa.demo@maya.local',
        'departamento' => 'ST_ESPA',
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
    [
        'id'           => '848dc299-240e-4a75-9d8e-f0a04089309d',
        'nombre'       => 'Super Admin',
        'email'        => 'superadmin@maya.local',
        'departamento' => 'Administración',
    ],
];
