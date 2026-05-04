<?php

/**
 * Asignaciones usuario ↔ jerarquía académica para mocks locales/testing.
 * `user_id` debe coincidir con `id` en {@see database/data/users_mock.php}.
 *
 * Idempotente: {@see \Database\Seeders\UserHierarchySeeder} usa insertOrIgnore.
 *
 * ── Referencia de usuarios ────────────────────────────────────────────────
 * ed568442  → Direccion Demo     → acceso global (todos los tipos, estudios y módulos)
 * 2ead4bf3  → Secretaria Demo    → acceso global (todos los tipos, estudios y módulos)
 * cf8bb92a  → Docente ESPA Demo  → ST_ESPA / S_ESPA / M_MAT_1, M_ENG_1, M_LEN_2
 * 53bc5feb  → Docente Bach Demo  → ST_BACH+ST_FP / S_BACH_1_C, S_BACH_2_C, S_FP_SMR_1 / M_FIS_1C, M_BIO_2C, M_SMR_MME
 * 50f503c6  → Docente FP Demo    → ST_FP / S_FP_DAW, S_FP_ASIR / M_DAW_DWECL, M_DAW_DWES, M_DAW_DIW, M_ASIR_SRI, M_ASIR_SAD, M_SMR_MME, M_SMR_PAR
 * f6bbe247  → Auditoría Demo     → permisos amplios en mock; sin jerarquía académica (solo revisión/auditoría)
 */
return [

    // ── Asignaciones usuario ↔ tipo de estudio ────────────────────────────
    'user_study_types' => [
        // Dirección: acceso global
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'study_type_id' => 'ST_ESPA'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'study_type_id' => 'ST_BACH'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'study_type_id' => 'ST_FP'],

        // Secretaría: acceso global
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'study_type_id' => 'ST_ESPA'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'study_type_id' => 'ST_BACH'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'study_type_id' => 'ST_FP'],

        // Docente ESPA
        ['user_id' => 'cf8bb92a-0417-4a4c-918a-08dd3fd69165', 'study_type_id' => 'ST_ESPA'],

        // Docente Bachillerato
        ['user_id' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f', 'study_type_id' => 'ST_BACH'],
        ['user_id' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f', 'study_type_id' => 'ST_FP'],

        // Docente FP / Cicles Formatius
        ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'study_type_id' => 'ST_FP'],
    ],

    // ── Asignaciones usuario ↔ estudio ────────────────────────────────────
    'user_studies' => [
        // Dirección: todos los estudios
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'study_id' => 'S_ESPA'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'study_id' => 'S_BACH_1_C'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'study_id' => 'S_BACH_2_C'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'study_id' => 'S_FP_DAW'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'study_id' => 'S_FP_ASIR'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'study_id' => 'S_FP_SMR_1'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'study_id' => 'S_FP_SMR_2'],

        // Secretaría: todos los estudios
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'study_id' => 'S_ESPA'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'study_id' => 'S_BACH_1_C'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'study_id' => 'S_BACH_2_C'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'study_id' => 'S_FP_DAW'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'study_id' => 'S_FP_ASIR'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'study_id' => 'S_FP_SMR_1'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'study_id' => 'S_FP_SMR_2'],

        // Docente ESPA
        ['user_id' => 'cf8bb92a-0417-4a4c-918a-08dd3fd69165', 'study_id' => 'S_ESPA'],

        // Docente Bachillerato
        ['user_id' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f', 'study_id' => 'S_BACH_1_C'],
        ['user_id' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f', 'study_id' => 'S_BACH_2_C'],
        ['user_id' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f', 'study_id' => 'S_FP_SMR_1'],

        // Docente FP / DAW
        ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'study_id' => 'S_FP_DAW'],
        ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'study_id' => 'S_FP_ASIR'],
    ],

    // ── Asignaciones usuario ↔ módulo ─────────────────────────────────────
    'user_course_modules' => [
        // Dirección: todos los módulos
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'module_id' => 'M_MAT_1'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'module_id' => 'M_ENG_1'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'module_id' => 'M_LEN_2'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'module_id' => 'M_FIS_1C'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'module_id' => 'M_BIO_2C'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'module_id' => 'M_DAW_DWECL'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'module_id' => 'M_DAW_DWES'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'module_id' => 'M_DAW_DIW'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'module_id' => 'M_ASIR_SRI'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'module_id' => 'M_ASIR_SAD'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'module_id' => 'M_SMR_MME'],
        ['user_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2', 'module_id' => 'M_SMR_PAR'],

        // Secretaría: todos los módulos
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'module_id' => 'M_MAT_1'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'module_id' => 'M_ENG_1'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'module_id' => 'M_LEN_2'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'module_id' => 'M_FIS_1C'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'module_id' => 'M_BIO_2C'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'module_id' => 'M_DAW_DWECL'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'module_id' => 'M_DAW_DWES'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'module_id' => 'M_DAW_DIW'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'module_id' => 'M_ASIR_SRI'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'module_id' => 'M_ASIR_SAD'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'module_id' => 'M_SMR_MME'],
        ['user_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664', 'module_id' => 'M_SMR_PAR'],

        // Docente ESPA
        ['user_id' => 'cf8bb92a-0417-4a4c-918a-08dd3fd69165', 'module_id' => 'M_MAT_1'],
        ['user_id' => 'cf8bb92a-0417-4a4c-918a-08dd3fd69165', 'module_id' => 'M_ENG_1'],
        ['user_id' => 'cf8bb92a-0417-4a4c-918a-08dd3fd69165', 'module_id' => 'M_LEN_2'],

        // Docente Bachillerato
        ['user_id' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f', 'module_id' => 'M_FIS_1C'],
        ['user_id' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f', 'module_id' => 'M_BIO_2C'],
        ['user_id' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f', 'module_id' => 'M_SMR_MME'],

        // Docente FP / ciclos (módulos de los estudios asignados en mock)
        ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'module_id' => 'M_DAW_DWECL'],
        ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'module_id' => 'M_DAW_DWES'],
        ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'module_id' => 'M_DAW_DIW'],
        ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'module_id' => 'M_ASIR_SRI'],
        ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'module_id' => 'M_ASIR_SAD'],
        ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'module_id' => 'M_SMR_MME'],
        ['user_id' => '50f503c6-cb63-466c-852d-0b30ae130e98', 'module_id' => 'M_SMR_PAR'],
    ],

];
