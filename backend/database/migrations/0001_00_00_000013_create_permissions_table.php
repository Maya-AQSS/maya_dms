<?php

use Maya\Platform\Database\PostgresFdwMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de permisos de DMS — consumido vía FDW desde maya_auth.v_dms_permissions.
 *
 * maya_authorization es la única fuente de verdad para este catálogo.
 *
 * Rutas:
 * - `testing` : tabla física `permissions` para que los tests unitarios funcionen sin FDW.
 * - `local`   : FDW loopback a maya_auth.v_dms_permissions (mismo Postgres, distinta BD).
 * - staging/prod: FDW remoto configurado en config('database.fdw.permissions').
 *
 * La PK lógica es `code` (alias de permissions.slug en maya_auth).
 * No se declara PK física en la FOREIGN TABLE ni en la vista (solo lectura vía FDW).
 */
return new class extends Migration
{
    private const VIEW_NAME = 'permissions';

    private const FDW_TABLE = 'permissions_fdw';

    private const FDW_SERVER = 'maya_auth_permissions_server';

    public function up(): void
    {
        if (app()->environment('testing')) {
            $this->createTestingPermissionsTable();

            return;
        }

        $this->setupFdw();
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            DB::statement('DROP TABLE IF EXISTS ' . self::VIEW_NAME . ' CASCADE');

            return;
        }

        PostgresFdwMigration::dropViewOrTableInPublic(self::VIEW_NAME);
        PostgresFdwMigration::dropForeignTableIfExists(self::FDW_TABLE);
        PostgresFdwMigration::dropFdwServerAndUserMapping(self::FDW_SERVER);
    }

    /**
     * Tabla física para tests (sin postgres_fdw). Misma interfaz que la vista FDW.
     */
    private function createTestingPermissionsTable(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS permissions (
                code        VARCHAR(191) PRIMARY KEY,
                name        VARCHAR(255),
                description TEXT,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    private function setupFdw(): void
    {
        $isLocal = app()->environment('local');

        if ($isLocal) {
            // En local apuntamos al mismo Postgres pero a la BD maya_auth.
            $host     = config('database.connections.pgsql.host');
            $port     = config('database.connections.pgsql.port');
            $database = config('database.fdw.permissions.database', 'maya_auth');
            $username = config('database.fdw.permissions.username', 'maya');
            $password = config('database.fdw.permissions.password', 'secret');
        } else {
            $host     = config('database.fdw.permissions.host');
            $port     = config('database.fdw.permissions.port');
            $database = config('database.fdw.permissions.database');
            $username = config('database.fdw.permissions.username');
            $password = config('database.fdw.permissions.password');
        }

        if (! PostgresFdwMigration::ensurePostgresFdwExtension('permissions catalog')) {
            return;
        }

        PostgresFdwMigration::createFdwServerWithUserMapping(
            self::FDW_SERVER,
            (string) $host,
            (string) $port,
            (string) $database,
            (string) $username,
            (string) $password,
        );

        $foreignColumnsSql = 'code VARCHAR(191), name VARCHAR(255), description TEXT, '
            . 'created_at TIMESTAMP, updated_at TIMESTAMP';

        $viewSelectSql = 'code, name, description, created_at, updated_at';

        PostgresFdwMigration::createForeignTableWithPassThroughView(
            self::VIEW_NAME,
            $foreignColumnsSql,
            $viewSelectSql,
            self::FDW_SERVER,
            'public',
            'v_dms_permissions',
        );
    }
};
