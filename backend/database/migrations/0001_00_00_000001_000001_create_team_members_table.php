<?php

use Maya\Platform\Database\PostgresFdwMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membresías de usuario en equipos (tabla externa vía FDW).
 *
 * Rutas (mismo patrón FDW que jerarquía y permisos):
 * - `testing`:            tabla física `team_members` (sin postgres_fdw).
 * - `local`:              `team_members_source` + foreign table + vista homónima vía FDW.
 * - staging/production:   FDW remoto según `config('database.fdw.team_members')`.
 *
 * Sin FK física sobre `team_id`: `teams` es vista FDW; PostgreSQL no admite REFERENCES a vistas.
 */
return new class extends Migration
{
    private const VIEW_NAME          = 'team_members';
    private const FDW_TABLE          = 'team_members_fdw';
    private const FDW_SERVER         = 'team_members_server';
    private const LOCAL_SOURCE_TABLE = 'team_members_source';

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
            CREATE TABLE IF NOT EXISTS team_members (
                id         UUID         PRIMARY KEY,
                team_id    UUID         NOT NULL,
                user_id    VARCHAR(255) NOT NULL,
                role       VARCHAR(50)  NOT NULL DEFAULT \'member\',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (team_id, user_id)
            )
        ');

        DB::statement('CREATE INDEX IF NOT EXISTS team_members_user_team_idx
            ON team_members (user_id, team_id)');
    }

    private function setupFdw(): void
    {
        // Los foreign tables no aparecen en pg_catalog.pg_tables, por lo que `migrate:fresh`
        // no los elimina. Hacemos DROP explícito para garantizar que el esquema sea correcto
        // aunque haya quedado un objeto antiguo de una ejecución previa.
        PostgresFdwMigration::dropViewOrTableInPublic(self::VIEW_NAME);
        PostgresFdwMigration::dropForeignTableIfExists(self::FDW_TABLE);

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
            $host     = config('database.fdw.team_members.host');
            $port     = config('database.fdw.team_members.port');
            $database = config('database.fdw.team_members.database');
            $username = config('database.fdw.team_members.username');
            $password = config('database.fdw.team_members.password');
            $schema   = config('database.fdw.team_members.schema', 'public');
            $source   = config('database.fdw.team_members.table', 'team_members');
        }

        if (! PostgresFdwMigration::ensurePostgresFdwExtension('team_members catalog')) {
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
            'id UUID, team_id UUID, user_id VARCHAR(255), role VARCHAR(50), created_at TIMESTAMP, updated_at TIMESTAMP',
            'id, team_id, user_id, role, created_at, updated_at',
            self::FDW_SERVER,
            (string) $schema,
            (string) $source,
        );
    }

    /**
     * Tabla fuente local en entorno `local`.
     * Sin FK sobre `team_id`: `teams` es vista FDW, PostgreSQL no admite REFERENCES a vistas.
     */
    private function createLocalSourceTable(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS team_members_source (
                id         UUID         PRIMARY KEY,
                team_id    UUID         NOT NULL,
                user_id    VARCHAR(255) NOT NULL,
                role       VARCHAR(50)  NOT NULL DEFAULT \'member\',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (team_id, user_id)
            )
        ');

        DB::statement('CREATE INDEX IF NOT EXISTS team_members_source_user_team_idx
            ON team_members_source (user_id, team_id)');
    }
};
