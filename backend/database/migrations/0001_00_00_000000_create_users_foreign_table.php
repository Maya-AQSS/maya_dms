<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            $this->createLocalTable();
            return;
        }

        $this->setupFdw();
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            DB::statement('DROP TABLE IF EXISTS users');
            return;
        }

        // users puede ser VIEW (caso normal) o TABLE (artefacto de entorno previo).
        // Se usa DO $$ para evitar abortar la transacción si el tipo no coincide.
        DB::statement("
            DO \$\$ BEGIN
                IF EXISTS (
                    SELECT 1 FROM information_schema.views
                    WHERE table_schema = 'public' AND table_name = 'users'
                ) THEN
                    DROP VIEW users;
                ELSIF EXISTS (
                    SELECT 1 FROM information_schema.tables
                    WHERE table_schema = 'public' AND table_name = 'users'
                    AND table_type = 'BASE TABLE'
                ) THEN
                    DROP TABLE users;
                END IF;
            END \$\$
        ");

        DB::statement('DROP FOREIGN TABLE IF EXISTS users_fdw');
        DB::statement('DROP USER MAPPING IF EXISTS FOR CURRENT_USER SERVER users_server');
        DB::statement('DROP SERVER IF EXISTS users_server CASCADE');

        if (app()->environment('local')) {
            DB::statement('DROP TABLE IF EXISTS users_source');
        }

        // La extensión puede haber sido creada por el superusuario de infra,
        // en cuyo caso maya_dms_user no tiene permisos para eliminarla.
        DB::statement("
            DO \$\$ BEGIN
                DROP EXTENSION IF EXISTS postgres_fdw;
            EXCEPTION WHEN insufficient_privilege THEN
                NULL; -- La extensión persiste; la gestiona el DBA de infra
            END \$\$
        ");
    }

    /**
     * SQLite-compatible stub for testing (no FDW support).
     */
    private function createLocalTable(): void
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
     * Configure postgres_fdw for both local and production.
     *
     * Local:      FDW server + extension pre-created by infra/init-databases.sh.
     *             Only USER MAPPING, FOREIGN TABLE and VIEW are created here.
     * Production: FDW points to the remote corporate database via FDW_USERS_* env vars.
     */
    private function setupFdw(): void
    {
        $isLocal = app()->environment('local');

        if ($isLocal) {
            $this->createLocalSourceTable();

            $user     = config('database.connections.pgsql.username');
            $password = config('database.connections.pgsql.password');
            $schema   = 'public';
            $table    = 'users_source';
        } else {
            $host     = config('database.fdw.users.host');
            $port     = config('database.fdw.users.port');
            $dbname   = config('database.fdw.users.database');
            $user     = config('database.fdw.users.username');
            $password = config('database.fdw.users.password');
            $schema   = config('database.fdw.users.schema', 'public');
            $table    = config('database.fdw.users.table', 'users');

            // En producción, la extensión y el server los gestiona el DBA.
            // Intentamos crearlos por si es un entorno sin init-databases.sh.
            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS postgres_fdw');
            } catch (\Throwable $e) {
                logger()->error("FDW: no permission for CREATE EXTENSION — ensure DBA has pre-created it");
                return;
            }

            $host   = addcslashes($host, "'\\");
            $port   = addcslashes($port, "'\\");
            $dbname = addcslashes($dbname, "'\\");

            DB::statement("
                CREATE SERVER IF NOT EXISTS users_server
                FOREIGN DATA WRAPPER postgres_fdw
                OPTIONS (host '{$host}', port '{$port}', dbname '{$dbname}')
            ");
        }

        $user     = addcslashes($user, "'\\");
        $password = addcslashes($password, "'\\");
        $schema   = addcslashes($schema, "'\\");
        $table    = addcslashes($table, "'\\");

        DB::statement("
            CREATE USER MAPPING IF NOT EXISTS FOR CURRENT_USER
            SERVER users_server
            OPTIONS (user '{$user}', password '{$password}')
        ");

        DB::statement("
            CREATE FOREIGN TABLE IF NOT EXISTS users_fdw (
                id           VARCHAR(255),
                nombre       VARCHAR(255),
                email        VARCHAR(255),
                departamento VARCHAR(255)
            )
            SERVER users_server
            OPTIONS (schema_name '{$schema}', table_name '{$table}')
        ");

        DB::statement("
            CREATE OR REPLACE VIEW users AS
            SELECT
                id,
                nombre as name,
                email,
                departamento as department
            FROM users_fdw
        ");

        $this->revokeWritePermissions();
    }

    /**
     * Stub table as data source for the local self-referencing FDW.
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

    private function revokeWritePermissions(): void
    {
        $appUser = config('database.connections.pgsql.username');

        if (empty($appUser)) {
            return;
        }

        try {
            DB::statement("REVOKE INSERT, UPDATE, DELETE ON users_fdw FROM \"{$appUser}\"");
            DB::statement("GRANT SELECT ON users_fdw TO \"{$appUser}\"");
        } catch (\Throwable $e) {
            logger()->warning("FDW: could not set permissions for {$appUser}: {$e->getMessage()}");
        }
    }
};
