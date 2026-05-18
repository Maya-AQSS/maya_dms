<?php

use Maya\Platform\Database\PostgresFdwMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nombre de la relación lógica que consume la app (`exists:teams,id`, modelo {@see \App\Models\Team}).
     *
     * Rutas (mismo patrón FDW que {@see 0001_00_00_000000_create_users_foreign_table}; catálogo distinto, servidor propio):
     * - `testing`: tabla física `teams` (sin postgres_fdw).
     * - `local`: `teams_source` + foreign table {@see self::FDW_TABLE} + vista homónima vía
     *   {@see PostgresFdwMigration::createForeignTableWithPassThroughView} y servidor {@see self::FDW_SERVER}.
     * - staging/production: FDW remoto según `config('database.fdw.teams')`.
     *
     * Mocks: {@see \Database\Seeders\TeamsSeeder} en `teams_source` (local) o tabla `teams` (testing), nunca en la vista.
     */
    private const VIEW_NAME = 'teams';

    /**
     * Nombre de la foreign table gestionada por postgres_fdw (siempre `{base}_fdw`).
     */
    private const FDW_TABLE = 'teams_fdw';

    /**
     * Servidor FDW propio del catálogo de equipos (no reutiliza `odoo_server`: otro origen / otro mapping).
     */
    private const FDW_SERVER = 'teams_server';

    /**
     * Tabla local escribible solo en `local`; la vista `teams` la lee a través del FDW.
     */
    private const LOCAL_SOURCE_TABLE = 'teams_source';

    public function up(): void
    {
        if (app()->environment('testing')) {
            $this->createTestingTeamsTable();

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
    private function createTestingTeamsTable(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS teams (
                id              VARCHAR(255) PRIMARY KEY,
                name            VARCHAR(255) NOT NULL,
                description     TEXT,
                owner_id        VARCHAR(255),
                is_department   BOOLEAN NOT NULL DEFAULT FALSE,
                created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at      TIMESTAMP NULL
            )
        ');
    }

    /**
     * Monta postgres_fdw: foreign table + vista de paso, igual que usuarios.
     * - `local`: conexión y credenciales de `pgsql` hacia `teams_source` en esta BD.
     * - otros: `database.fdw.teams.*` hacia el catálogo remoto real.
     */
    private function setupFdw(): void
    {
        $isLocal = app()->environment('local');

        if ($isLocal) {
            $this->createLocalTeamsSourceTable();

            $host = config('database.connections.pgsql.host');
            $port = config('database.connections.pgsql.port');
            $database = config('database.connections.pgsql.database');
            $username = config('database.connections.pgsql.username');
            $password = config('database.connections.pgsql.password');
            $schema = 'public';
            $sourceTable = self::LOCAL_SOURCE_TABLE;
        } else {
            $host = config('database.fdw.teams.host');
            $port = config('database.fdw.teams.port');
            $database = config('database.fdw.teams.database');
            $username = config('database.fdw.teams.username');
            $password = config('database.fdw.teams.password');
            $schema = config('database.fdw.teams.schema', 'public');
            $sourceTable = config('database.fdw.teams.table', 'teams');
        }

        if (! PostgresFdwMigration::ensurePostgresFdwExtension('teams catalog')) {
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

        $foreignColumnsSql = 'id VARCHAR(255), name VARCHAR(255), description TEXT, owner_id VARCHAR(255), '
            .'is_department BOOLEAN, created_at TIMESTAMP, updated_at TIMESTAMP, deleted_at TIMESTAMP';

        $viewSelectSql = 'id, name, description, owner_id, is_department, created_at, updated_at, deleted_at';

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
     * Fuente local para simular catálogo corporativo en entorno `local` (rellenable con mocks).
     */
    private function createLocalTeamsSourceTable(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS teams_source (
                id              VARCHAR(255) PRIMARY KEY,
                name            VARCHAR(255) NOT NULL,
                description     TEXT,
                owner_id        VARCHAR(255),
                is_department   BOOLEAN NOT NULL DEFAULT FALSE,
                created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at      TIMESTAMP NULL
            )
        ');
    }
};
