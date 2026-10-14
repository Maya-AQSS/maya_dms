<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Maya\Platform\Database\PostgresFdwMigration;

/**
 * Permisos resueltos del usuario desde la vista común `v_portal_user_permissions`
 * de maya_auth, como el resto de apps, en lugar de `v_dms_user_permissions`.
 *
 * La foreign table `user_resolved_permissions_fdw` (y su vista pass-through
 * `user_resolved_permissions`) la crea la migración de shared-profile-laravel
 * con `database.fdw.user_permissions.remote_view`. Esa migración ya se ejecutó
 * donde la app está desplegada, así que cambiar el valor no basta: aquí se
 * re-apunta la foreign table a la vista configurada. Las columnas son las mismas
 * (`user_id`, `permission_slug`); la vista común trae además los slugs de las
 * demás apps (incluidos los `*.login`), que dms ya espera en `/me`.
 *
 * Idempotente: en una instalación nueva la foreign table ya nace con la vista
 * configurada y esto no cambia nada.
 */
return new class extends Migration
{
    private const FDW_TABLE = 'user_resolved_permissions_fdw';

    private const PREVIOUS_REMOTE_VIEW = 'v_dms_user_permissions';

    private function isTestEnv(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }
        $db = config('database.connections.pgsql.database');

        return is_string($db) && str_ends_with($db, '_test');
    }

    public function up(): void
    {
        $remoteView = (string) config('database.fdw.user_permissions.remote_view', 'v_portal_user_permissions');

        $this->pointForeignTableTo($remoteView);
    }

    public function down(): void
    {
        $this->pointForeignTableTo(self::PREVIOUS_REMOTE_VIEW);
    }

    private function pointForeignTableTo(string $remoteView): void
    {
        // En testing `user_resolved_permissions` es una tabla física (sin FDW).
        if ($this->isTestEnv() || ! $this->foreignTableExists()) {
            return;
        }

        DB::statement(
            'ALTER FOREIGN TABLE '.self::FDW_TABLE
            ." OPTIONS (SET table_name '".PostgresFdwMigration::escapeSqlLiteral($remoteView)."')"
        );
    }

    private function foreignTableExists(): bool
    {
        return DB::selectOne(
            "select 1 as x from information_schema.foreign_tables where foreign_table_schema = 'public' and foreign_table_name = ? limit 1",
            [self::FDW_TABLE],
        ) !== null;
    }
};
