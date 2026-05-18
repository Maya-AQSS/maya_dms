<?php

/**
 * Catálogo reducido de usuarios mock (local / pruebas con Keycloak).
 * Campos: id, nombre, first_name, last_name, email, departamento.
 *
 * `departamento` es metadato mock para mapear el rol académico en seeds y no
 * se expone a través de la vista FDW `users`. `first_name` y `last_name` los
 * usan los seeders para poblar la tabla stub local y permitir que /me y la
 * auditoría dispongan siempre de nombre+apellido aunque Keycloak User
 * Federation no esté sincronizado en el primer login.
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
        'first_name'   => 'Direccion',
        'last_name'    => 'Demo',
        'email'        => 'direccion.demo@maya.local',
        'departamento' => 'Dirección',
    ],
    [
        'id'           => '2ead4bf3-574c-41b4-95ca-cac7daed0664',
        'nombre'       => 'Secretaria Demo',
        'first_name'   => 'Secretaria',
        'last_name'    => 'Demo',
        'email'        => 'secretaria.demo@maya.local',
        'departamento' => 'Secretaría',
    ],
    [
        'id'           => 'cf8bb92a-0417-4a4c-918a-08dd3fd69165',
        'nombre'       => 'Docente ESPA Demo',
        'first_name'   => 'Docente ESPA',
        'last_name'    => 'Demo',
        'email'        => 'docente.espa.demo@maya.local',
        'departamento' => 'ST_ESPA',
    ],
    [
        'id'           => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f',
        'nombre'       => 'Docente Bachillerato Demo',
        'first_name'   => 'Docente Bachillerato',
        'last_name'    => 'Demo',
        'email'        => 'docente.bach.demo@maya.local',
        'departamento' => 'ST_BACH',
    ],
    [
        'id'           => '50f503c6-cb63-466c-852d-0b30ae130e98',
        'nombre'       => 'Docente FP Demo',
        'first_name'   => 'Docente FP',
        'last_name'    => 'Demo',
        'email'        => 'docente.fp.demo@maya.local',
        'departamento' => 'ST_FP',
    ],
    [
        'id'           => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc',
        'nombre'       => 'Auditoría Demo',
        'first_name'   => 'Auditoría',
        'last_name'    => 'Demo',
        'email'        => 'auditor.demo@maya.local',
        'departamento' => 'Auditoría',
    ],
    [
        'id'           => '848dc299-240e-4a75-9d8e-f0a04089309d',
        'nombre'       => 'Super Admin',
        'first_name'   => 'Super',
        'last_name'    => 'Admin',
        'email'        => 'superadmin@maya.local',
        'departamento' => 'Administración',
    ],
];
