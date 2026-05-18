<?php

use Maya\Platform\Database\PostgresFdwMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asignaciones usuario ↔ permiso (identificador de usuario = claim `sub` de Keycloak o id mock FDW).
 *
 * Rutas (mismo patrón FDW que {@see 0001_00_00_000000_create_users_foreign_table}):
 * - `testing`: tabla física `user_permissions` (sin postgres_fdw).
 * - `local`: `user_permissions_source` + foreign table {@see self::FDW_TABLE} + vista homónima.
 * - staging/production: FDW remoto según `config('database.fdw.user_permissions')`.
 *
 * `permission_code` referencia {@see \App\Models\Permission} vía `permissions.code`.
 */
return new class extends Migration
{
    private const VIEW_NAME = 'user_permissions';

    private const FDW_TABLE = 'user_permissions_fdw';

    private const FDW_SERVER = 'user_permissions_server';

    private const LOCAL_SOURCE_TABLE = 'user_permissions_source';

    public function up(): void
    {
        if (app()->environment('testing')) {
            $this->createTestingUserPermissionsTable();

            return;
        }

        $this->setupFdw();
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            DB::statement('DROP TABLE IF EXISTS '.self::VIEW_NAME);

            return;
        }

        PostgresFdwMigration::dropViewOrTableInPublic(self::VIEW_NAME);

        PostgresFdwMigration::dropForeignTableIfExists(self::FDW_TABLE);
        PostgresFdwMigration::dropFdwServerAndUserMapping(self::FDW_SERVER);

        if (app()->environment('local')) {
            DB::statement('DROP TABLE IF EXISTS '.self::LOCAL_SOURCE_TABLE);
        }
    }

    /**
     * Tabla local de solo testing (SQLite / PostgreSQL de tests).
     */
    private function createTestingUserPermissionsTable(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS user_permissions (
                id                VARCHAR(255) PRIMARY KEY,
                user_id           VARCHAR(255) NOT NULL,
                permission_code   VARCHAR(191) NOT NULL
                    REFERENCES permissions(code) ON DELETE CASCADE,
                created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS user_permissions_user_permission_uidx
            ON user_permissions (user_id, permission_code)');
        DB::statement('CREATE INDEX IF NOT EXISTS user_permissions_user_id_idx ON user_permissions (user_id)');
    }

    private function setupFdw(): void
    {
        $isLocal = app()->environment('local');

        if ($isLocal) {
            $this->createLocalUserPermissionsSourceTable();

            $host = config('database.connections.pgsql.host');
            $port = config('database.connections.pgsql.port');
            $database = config('database.connections.pgsql.database');
            $username = config('database.connections.pgsql.username');
            $password = config('database.connections.pgsql.password');
            $schema = 'public';
            $sourceTable = self::LOCAL_SOURCE_TABLE;
        } else {
            $host = config('database.fdw.user_permissions.host');
            $port = config('database.fdw.user_permissions.port');
            $database = config('database.fdw.user_permissions.database');
            $username = config('database.fdw.user_permissions.username');
            $password = config('database.fdw.user_permissions.password');
            $schema = config('database.fdw.user_permissions.schema', 'public');
            $sourceTable = config('database.fdw.user_permissions.table', 'user_permissions');
        }

        if (! PostgresFdwMigration::ensurePostgresFdwExtension('user_permissions catalog')) {
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

        $foreignColumnsSql = 'id VARCHAR(255), user_id VARCHAR(255), permission_code VARCHAR(191), '
            .'created_at TIMESTAMP, updated_at TIMESTAMP';

        $viewSelectSql = 'id, user_id, permission_code, created_at, updated_at';

        PostgresFdwMigration::createForeignTableWithPassThroughView(
            self::VIEW_NAME,
            $foreignColumnsSql,
            $viewSelectSql,
            self::FDW_SERVER,
            (string) $schema,
            (string) $sourceTable,
        );
    }

    /**
     * Fuente local escribible en `local` (mocks / seeds); la vista `user_permissions` lee vía FDW.
     *
     * No FK a permissions.code: en local/prod permissions es una VIEW FDW (read-only)
     * y PostgreSQL no admite FOREIGN KEY contra vistas.
     */
    private function createLocalUserPermissionsSourceTable(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS user_permissions_source (
                id                VARCHAR(255) PRIMARY KEY,
                user_id           VARCHAR(255) NOT NULL,
                permission_code   VARCHAR(191) NOT NULL,
                created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (user_id, permission_code)
            )
        ');

        DB::statement('CREATE INDEX IF NOT EXISTS user_permissions_source_user_id_idx
            ON user_permissions_source (user_id)');
    }
};
