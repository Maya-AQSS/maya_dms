<?php

declare(strict_types=1);

/**
 * UUIDs de usuarios dev del ecosistema Maya (fuente única para seeds DMS).
 *
 * Deben coincidir con:
 * - maya_infra/docker/keycloak/realm-export.template.json
 * - maya_infra/docker/postgres/seeds/seed-user-academic-assignments.sh
 *
 * @return array<string, string> clave lógica → UUID Keycloak
 */
return [
    'superadmin' => '6555faeb-aad4-468c-9d8b-a3917c118af5',
    'direccion' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
    'secretaria' => '2ead4bf3-574c-41b4-95ca-cac7daed0664',
    'docente_c' => 'cf8bb92a-0417-4a4c-918a-08dd3fd69165',
    'docente_b' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f',
    'docente_i' => '50f503c6-cb63-466c-852d-0b30ae130e98',
    'docente_fol' => 'aae68e43-755a-4c5b-9650-8d80f4c5f43e',
    'auditor' => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc',
    'jefe_d_c' => '8f3e2a1b-4c5d-6e7f-8a9b-0c1d2e3f4a5b',
    'jefe_d_i' => 'bde2645d-198e-48ed-9828-4d51f016495c',
    'jefe_d_fol' => '603efab6-3b61-4683-b410-73086cc7ed47',
    'jefe_e_bach' => 'b7c4d2e1-9a3f-4e6b-8c0d-1e2f3a4b5c6d',
    'jefe_e_fp' => 'd7ede560-ae95-4bc4-a9f1-4c5338a98197',
];
