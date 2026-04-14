<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Utilidades compartidas entre migraciones que montan catálogo vía postgres_fdw.
 *
 * Los nombres de relación pasados como identificadores deben ser valores controlados
 * por la migración (constantes), no entrada de usuario.
 */
final class PostgresFdwMigration
{
    /**
     * Escapa literales interpolados en OPTIONS (schema_name, table_name, etc.).
     */
    public static function escapeSqlLiteral(string $value): string
    {
        return addcslashes($value, "'\\");
    }

    /**
     * Elimina una vista o tabla base en `public` sin abortar si el tipo no coincide.
     */
    public static function dropViewOrTableInPublic(string $relationName): void
    {
        $safeName = self::escapeSqlLiteral($relationName);

        DB::statement("
            DO \$\$ BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM information_schema.views
                    WHERE table_schema = 'public'
                      AND table_name = '{$safeName}'
                ) THEN
                    EXECUTE 'DROP VIEW ' || quote_ident('{$safeName}');
                ELSIF EXISTS (
                    SELECT 1
                    FROM information_schema.tables
                    WHERE table_schema = 'public'
                      AND table_name = '{$safeName}'
                      AND table_type = 'BASE TABLE'
                ) THEN
                    EXECUTE 'DROP TABLE ' || quote_ident('{$safeName}');
                END IF;
            END \$\$
        ");
    }

    public static function dropForeignTableIfExists(string $fdwTableName): void
    {
        DB::statement('DROP FOREIGN TABLE IF EXISTS '.$fdwTableName.' CASCADE');
    }

    /**
     * Crea `{base}_fdw` apuntando a `remoteSchema.remoteRelation` y una vista `{base}` con SELECT directo.
     */
    public static function createForeignTableWithPassThroughView(
        string $catalogBaseName,
        string $foreignColumnsSql,
        string $viewSelectSql,
        string $fdwServer,
        string $remoteSchema,
        string $remoteRelationName,
    ): void {
        $fdwTable = $catalogBaseName.'_fdw';
        $safeSchema = self::escapeSqlLiteral($remoteSchema);
        $safeRelation = self::escapeSqlLiteral($remoteRelationName);

        DB::statement('
            CREATE FOREIGN TABLE IF NOT EXISTS '.$fdwTable.' (
                '.$foreignColumnsSql.'
            )
            SERVER '.$fdwServer.'
            OPTIONS (schema_name \''.$safeSchema.'\', table_name \''.$safeRelation.'\')
        ');

        DB::statement('
            CREATE OR REPLACE VIEW '.$catalogBaseName.' AS
            SELECT '.$viewSelectSql.'
            FROM '.$fdwTable.'
        ');

        self::revokeAppUserWriteOnFdwRelation($fdwTable);
    }

    /**
     * Fuerza solo lectura sobre la foreign table para el usuario de aplicación de PostgreSQL.
     */
    public static function revokeAppUserWriteOnFdwRelation(string $fdwTableName): void
    {
        $appUser = config('database.connections.pgsql.username');

        if ($appUser === null || $appUser === '') {
            return;
        }

        try {
            DB::statement('REVOKE INSERT, UPDATE, DELETE ON '.$fdwTableName.' FROM "'.$appUser.'"');
            DB::statement('GRANT SELECT ON '.$fdwTableName.' TO "'.$appUser.'"');
        } catch (\Throwable $e) {
            logger()->warning("FDW: could not set permissions for {$appUser} on {$fdwTableName}: {$e->getMessage()}");
        }
    }
}
