<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('local', 'testing')) {
            $this->createLocalTable();
        } else {
            $this->setupFdw();
        }
    }

    public function down(): void
    {
        if (app()->environment('local', 'testing')) {
            DB::statement('DROP TABLE IF EXISTS users');
        } else {
            DB::statement('DROP VIEW IF EXISTS users');
            DB::statement('DROP FOREIGN TABLE IF EXISTS fdw_users');
            DB::statement('DROP USER MAPPING IF EXISTS FOR CURRENT_USER SERVER users_server');
            DB::statement('DROP SERVER IF EXISTS users_server CASCADE');
        }
    }

    private function createLocalTable(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS users (
                id         VARCHAR(255) PRIMARY KEY,
                email      VARCHAR(255) NOT NULL UNIQUE,
                name       VARCHAR(255),
                first_name VARCHAR(150),
                last_name  VARCHAR(150),
                username   VARCHAR(150),
                is_active  BOOLEAN NOT NULL DEFAULT true,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW()
            )
        ');
    }

    private function setupFdw(): void
    {
        $host     = config('database.fdw.users.host');
        $port     = config('database.fdw.users.port');
        $dbname   = config('database.fdw.users.database');
        $user     = config('database.fdw.users.username');
        $password = config('database.fdw.users.password');
        $schema   = config('database.fdw.users.schema', 'public');
        $table    = config('database.fdw.users.table', 'users');

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgres_fdw');

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
            CREATE FOREIGN TABLE IF NOT EXISTS fdw_users (
                id         VARCHAR(255),
                email      VARCHAR(255),
                first_name VARCHAR(150),
                last_name  VARCHAR(150),
                username   VARCHAR(150),
                enabled    BOOLEAN
            )
            SERVER users_server
            OPTIONS (schema_name '{$schema}', table_name '{$table}')
        ");

        DB::statement("
            CREATE OR REPLACE VIEW users AS
            SELECT
                id,
                email,
                COALESCE(NULLIF(CONCAT(first_name, ' ', last_name), ' '), username) AS name,
                first_name,
                last_name,
                username,
                enabled AS is_active
            FROM fdw_users
        ");
    }
};
