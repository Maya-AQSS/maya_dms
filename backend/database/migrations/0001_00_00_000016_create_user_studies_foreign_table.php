<?php

use Maya\Platform\Database\PostgresFdwMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asignaciones usuario ↔ estudio (claim `sub` de Keycloak o id mock FDW).
 *
 * Rutas (mismo patrón FDW que {@see 0001_00_00_000014_create_user_permissions_foreign_table}):
 * - `testing`: tabla física `user_studies` (sin postgres_fdw).
 * - `local`:   `user_studies_source` + foreign table + vista homónima vía FDW.
 * - staging/production: FDW remoto según `config('database.fdw.user_studies')`.
 *
 * `study_id` referencia `studies.id` (catálogo creado por la migración 0012).
 */
return new class extends Migration
{
    private const VIEW_NAME         = 'user_studies';
    private const FDW_TABLE         = 'user_studies_fdw';
    private const FDW_SERVER        = 'user_studies_server';
    private const LOCAL_SOURCE_TABLE = 'user_studies_source';

    public function up(): void
    {
        if (app()->environment('testing')) {
            $this->createTestingTable();
            return;
        }

        $this->setupFdw();
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            DB::statement('DROP TABLE IF EXISTS ' . self::VIEW_NAME);
            return;
        }

        PostgresFdwMigration::dropViewOrTableInPublic(self::VIEW_NAME);
        PostgresFdwMigration::dropForeignTableIfExists(self::FDW_TABLE);
        PostgresFdwMigration::dropFdwServerAndUserMapping(self::FDW_SERVER);

        if (app()->environment('local')) {
            DB::statement('DROP TABLE IF EXISTS ' . self::LOCAL_SOURCE_TABLE);
        }
    }

    /**
     * Tabla física para entorno testing (sin FDW).
     */
    private function createTestingTable(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS user_studies (
                id         VARCHAR(255) PRIMARY KEY,
                user_id    VARCHAR(255) NOT NULL,
                study_id   VARCHAR(255) NOT NULL
                    REFERENCES studies(id) ON DELETE CASCADE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS user_studies_user_study_uidx
            ON user_studies (user_id, study_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS user_studies_user_id_idx
            ON user_studies (user_id)');
    }

    private function setupFdw(): void
    {
        $isLocal = app()->environment('local');

        if ($isLocal) {
            $this->createLocalSourceTable();

            $host     = config('database.connections.pgsql.host');
            $port     = config('database.connections.pgsql.port');
            $database = config('database.connections.pgsql.database');
            $username = config('database.connections.pgsql.username');
            $password = config('database.connections.pgsql.password');
            $schema   = 'public';
            $source   = self::LOCAL_SOURCE_TABLE;
        } else {
            $host     = config('database.fdw.user_studies.host');
            $port     = config('database.fdw.user_studies.port');
            $database = config('database.fdw.user_studies.database');
            $username = config('database.fdw.user_studies.username');
            $password = config('database.fdw.user_studies.password');
            $schema   = config('database.fdw.user_studies.schema', 'public');
            $source   = config('database.fdw.user_studies.table', 'user_studies');
        }

        if (! PostgresFdwMigration::ensurePostgresFdwExtension('user_studies catalog')) {
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

        PostgresFdwMigration::createForeignTableWithPassThroughView(
            self::VIEW_NAME,
            'id VARCHAR(255), user_id VARCHAR(255), study_id VARCHAR(255), created_at TIMESTAMP, updated_at TIMESTAMP',
            'id, user_id, study_id, created_at, updated_at',
            self::FDW_SERVER,
            (string) $schema,
            (string) $source,
        );
    }

    /**
     * Tabla fuente local en entorno `local`.
     * Referencia a `studies` (tabla directa creada por la migración 0012 en local).
     */
    private function createLocalSourceTable(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS user_studies_source (
                id         VARCHAR(255) PRIMARY KEY,
                user_id    VARCHAR(255) NOT NULL,
                study_id   VARCHAR(255) NOT NULL
                    REFERENCES studies(id) ON DELETE CASCADE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (user_id, study_id)
            )
        ');

        DB::statement('CREATE INDEX IF NOT EXISTS user_studies_source_user_id_idx
            ON user_studies_source (user_id)');
    }
};
