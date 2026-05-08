<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        if (DB::getDriverName() !== 'pgsql') {
            Schema::dropIfExists(self::VIEW);
            Schema::create(self::VIEW, function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('username')->nullable();
                $table->string('employee_id')->nullable();
                $table->string('dni')->nullable();
                $table->string('employee_type')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            return;
        }

        $dbUser = config('database.connections.pgsql.username', 'maya_dms_user');

        // Idempotente: drop primero para que migrate:fresh no falle
        DB::statement('DROP VIEW IF EXISTS ' . self::VIEW . ' CASCADE');
        DB::statement('DROP FOREIGN TABLE IF EXISTS ' . self::FDW_TBL . ' CASCADE');

        DB::statement("
            CREATE FOREIGN TABLE " . self::FDW_TBL . " (
                id            varchar(255) NOT NULL,
                email         varchar(255) NOT NULL,
                display_name  varchar(255),
                first_name    varchar(150),
                last_name     varchar(150),
                username      varchar(150),
                employee_id   varchar(64),
                dni           varchar(32),
                employee_type varchar(64),
                is_active     boolean NOT NULL DEFAULT true
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
                   username,
                   employee_id,
                   dni,
                   employee_type,
                   is_active
            FROM " . self::FDW_TBL . "
        ");

        DB::statement("GRANT SELECT ON " . self::FDW_TBL . " TO \"{$dbUser}\"");
        DB::statement("GRANT SELECT ON " . self::VIEW . " TO \"{$dbUser}\"");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            Schema::dropIfExists(self::VIEW);

            return;
        }

        DB::statement('DROP VIEW IF EXISTS ' . self::VIEW . ' CASCADE');
        DB::statement('DROP FOREIGN TABLE IF EXISTS ' . self::FDW_TBL . ' CASCADE');
    }
};
