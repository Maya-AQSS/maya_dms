<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Migración histórica: antes añadía `department` a la vista FDW `users`.
 * Esa columna no forma parte del contrato con `v_app_users`; el código ya no la usa.
 * Se deja como no-op para no invalidar instalaciones que ya registraron este batch.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intencionalmente vacío: ver 0001_00_00_000029_restore_users_fdw_view_without_department.
    }

    public function down(): void
    {
        // Intencionalmente vacío.
    }
};
