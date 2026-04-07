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

        DB::statement('DROP VIEW IF EXISTS users');
        DB::statement('DROP FOREIGN TABLE IF EXISTS users_fdw');
        DB::statement('DROP USER MAPPING IF EXISTS FOR CURRENT_USER SERVER users_server');
        DB::statement('DROP SERVER IF EXISTS users_server CASCADE');

        if (app()->environment('local')) {
            DB::statement('DROP TABLE IF EXISTS users_source');
        }

        DB::statement('DROP EXTENSION IF EXISTS postgres_fdw');
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
     * Local:      FDW points to the same database (self-referencing via users_source table).
     * Production: FDW points to the remote corporate database via FDW_USERS_* env vars.
     */
    private function setupFdw(): void
    {
        $isLocal = app()->environment('local');

        if ($isLocal) {
            $this->createLocalSourceTable();

            $host     = config('database.connections.pgsql.host');
            $port     = config('database.connections.pgsql.port');
            $dbname   = config('database.connections.pgsql.database');
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
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgres_fdw');
        } catch (\Throwable $e) {
            logger()->error("No permission for postgres_fdw");
            return;
        }

        $host     = addcslashes($host, "'\\");
        $port     = addcslashes($port, "'\\");
        $dbname   = addcslashes($dbname, "'\\");
        $user     = addcslashes($user, "'\\");
        $password = addcslashes($password, "'\\");
        $schema   = addcslashes($schema, "'\\");
        $table    = addcslashes($table, "'\\");

        DB::statement("
            CREATE SERVER IF NOT EXISTS users_server
            FOREIGN DATA WRAPPER postgres_fdw
            OPTIONS (host '{$host}', port '{$port}', dbname '{$dbname}')
        ");

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
        } catch (\Throwable $e) {
            logger()->warning("FDW: could not revoke write permissions for {$appUser}: {$e->getMessage()}");
        }
    }
};
