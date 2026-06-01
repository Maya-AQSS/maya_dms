<?php

declare(strict_types=1);

use Symfony\Component\Uid\Uuid;

/**
 * UUIDs de usuarios dev para seeds DMS (clave lógica → id en vista FDW `users`).
 *
 * Base: realm-export.template.json / seed-user-academic-assignments.sh.
 * En Docker (reset-all + db:seed), Keycloak y Odoo usan uuid5 por slot; con
 * MAYA_SLOT en .env los seeds obtienen el mismo id que el JWT y el FDW.
 *
 * Tras reset: `php artisan db:seed` en el contenedor backend (MAYA_SLOT=dev, etc.).
 */

/**
 * Guard contra redeclaración: el archivo se `require`-a (no `require_once`)
 * desde varios seeders/data-files para que cada uno reciba el array via
 * `return` final. Sin el guard, el segundo include intenta redeclarar la
 * función y dispara "Cannot redeclare function" durante `db:seed`.
 *
 * @return array<string, string>
 */
if (! function_exists('maya_dev_user_ids')) {
    function maya_dev_user_ids(): array
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        /** @var array<string, string> */
        $realmExport = [
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

        $slot = $_ENV['MAYA_SLOT'] ?? getenv('MAYA_SLOT') ?: null;
        if (! is_string($slot) || $slot === '') {
            $resolved = $realmExport;

            return $resolved;
        }

        $namespace = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_DNS), 'maya-slot-'.$slot);

        $resolved = array_map(
            static fn (string $realmUserId): string => Uuid::v5($namespace, $realmUserId)->toRfc4122(),
            $realmExport
        );

        return $resolved;
    }
}

return maya_dev_user_ids();
