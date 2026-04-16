<?php

use App\Support\PostgresFdwMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nombre de la vista pública que consume la aplicación.
     */
    private const VIEW_NAME = 'users';

    /**
     * Nombre de la foreign table física gestionada por FDW.
     */
    private const FDW_TABLE = 'users_fdw';

    /**
     * Nombre del servidor FDW compartido en el esquema.
     */
    private const FDW_SERVER = 'users_server';

    /**
     * Tabla local usada como origen de datos en entorno local.
     */
    private const LOCAL_SOURCE_TABLE = 'users_source';

    public function up(): void
    {
        if (app()->environment('testing')) {
            $this->createTestingUsersTable();
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

        // La extensión puede ser gestionada por infra (sin permisos para borrarla).
        DB::statement("
            DO \$\$ BEGIN
                DROP EXTENSION IF EXISTS postgres_fdw;
            EXCEPTION WHEN insufficient_privilege THEN
                NULL;
            END \$\$
        ");
    }

    /**
     * Tabla local de solo testing (SQLite compatible).
     */
    private function createTestingUsersTable(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS users (
                id           VARCHAR(255) PRIMARY KEY,
                name         VARCHAR(255),
                email        VARCHAR(255) NOT NULL UNIQUE,
                department   VARCHAR(255),
                created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    /**
     * Configura FDW para local y producción.
     * - local: apunta a users_source (misma BD)
     * - producción: apunta a BD externa (config database.fdw.users.*)
     */
    private function setupFdw(): void
    {
        $isLocal = app()->environment('local');

        if ($isLocal) {
            $this->createLocalSourceTable();

            $host = config('database.connections.pgsql.host');
            $port = config('database.connections.pgsql.port');
            $database = config('database.connections.pgsql.database');
            $username = config('database.connections.pgsql.username');
            $password = config('database.connections.pgsql.password');
            $schema = 'public';
            $sourceTable = self::LOCAL_SOURCE_TABLE;
        } else {
            $host = config('database.fdw.users.host');
            $port = config('database.fdw.users.port');
            $database = config('database.fdw.users.database');
            $username = config('database.fdw.users.username');
            $password = config('database.fdw.users.password');
            $schema = config('database.fdw.users.schema', 'public');
            $sourceTable = config('database.fdw.users.table', 'users');
        }

        if (! PostgresFdwMigration::ensurePostgresFdwExtension('users catalog')) {
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

        $foreignColumnsSql = 'id VARCHAR(255), nombre VARCHAR(255), email VARCHAR(255), departamento VARCHAR(255)';
        $viewSelectSql = 'id, nombre AS name, email, departamento AS department';

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
     * Fuente local para simular origen externo en entorno local.
     */
    private function createLocalSourceTable(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS users_source (
                id           VARCHAR(255) PRIMARY KEY,
                nombre       VARCHAR(255),
                email        VARCHAR(255) NOT NULL UNIQUE,
                departamento VARCHAR(255),
                created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

};