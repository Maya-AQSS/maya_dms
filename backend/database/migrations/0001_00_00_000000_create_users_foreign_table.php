<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FDW directo a Odoo.v_app_users para maya_dms_db.
 *
 * El servidor "odoo_server" y el user mapping son creados por init-databases.sh
 * como superuser maya, ya que maya_dms_user no tiene privilegios suficientes.
 * Esta migración sólo crea la foreign table y la view.
 */
return new class extends Migration
{
    private const SERVER  = 'odoo_server';
    private const FDW_TBL = 'users_fdw';
    private const VIEW    = 'users';

    public function up(): void
    {
        $dbUser = config('database.connections.pgsql.username', 'maya_dms_user');

        // Idempotente: drop primero para que migrate:fresh no falle
        DB::statement('DROP VIEW IF EXISTS ' . self::VIEW . ' CASCADE');
        DB::statement('DROP FOREIGN TABLE IF EXISTS ' . self::FDW_TBL . ' CASCADE');

        DB::statement("
            CREATE FOREIGN TABLE " . self::FDW_TBL . " (
                id           varchar(255) NOT NULL,
                email        varchar(255) NOT NULL,
                display_name varchar(255),
                first_name   varchar(150),
                last_name    varchar(150),
                username     varchar(150),
                is_active    boolean NOT NULL DEFAULT true
            )
            SERVER " . self::SERVER . "
            OPTIONS (schema_name 'public', table_name 'v_app_users')
        ");

        DB::statement("
            CREATE VIEW " . self::VIEW . " AS
            SELECT id,
                   display_name AS name,
                   email,
                   first_name,
                   last_name,
                   is_active
            FROM " . self::FDW_TBL . "
        ");

        DB::statement("GRANT SELECT ON " . self::FDW_TBL . " TO \"{$dbUser}\"");
        DB::statement("GRANT SELECT ON " . self::VIEW . " TO \"{$dbUser}\"");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS ' . self::VIEW . ' CASCADE');
        DB::statement('DROP FOREIGN TABLE IF EXISTS ' . self::FDW_TBL . ' CASCADE');
    }
};
