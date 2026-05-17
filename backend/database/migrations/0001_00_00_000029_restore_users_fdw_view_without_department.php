<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vista `users` alineada con FDW (`v_app_users`): sin columna `department`.
 *
 * Testing: no-op — la migración 0000 ya crea una tabla física `users` sin FDW.
 * Producción/staging: recrea la vista sobre users_fdw sin la columna department.
 */
return new class extends Migration
{
    private const FDW_TBL = 'users_fdw';

    private const VIEW = 'users';

    public function up(): void
    {
        if ($this->isTestEnv() || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP VIEW IF EXISTS '.self::VIEW);

        DB::statement('
            CREATE VIEW '.self::VIEW.' AS
            SELECT id,
                   display_name AS name,
                   email,
                   first_name,
                   last_name,
                   username,
                   employee_id,
                   dni,
                   employee_type,
                   is_active
            FROM '.self::FDW_TBL.'
        ');

        $dbUser = config('database.connections.pgsql.username', 'maya_dms_user');
        DB::statement('GRANT SELECT ON '.self::VIEW.' TO "'.$dbUser.'"');
    }

    public function down(): void
    {
        if ($this->isTestEnv() || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP VIEW IF EXISTS '.self::VIEW);

        DB::statement('
            CREATE VIEW '.self::VIEW.' AS
            SELECT id,
                   display_name AS name,
                   email,
                   NULL::varchar(255) AS department,
                   first_name,
                   last_name,
                   username,
                   employee_id,
                   dni,
                   employee_type,
                   is_active
            FROM '.self::FDW_TBL.'
        ');

        $dbUser = config('database.connections.pgsql.username', 'maya_dms_user');
        DB::statement('GRANT SELECT ON '.self::VIEW.' TO "'.$dbUser.'"');
    }

    private function isTestEnv(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $db = config('database.connections.pgsql.database');
        return is_string($db) && str_ends_with($db, '_test');
    }
};
