<?php

declare(strict_types=1);

use App\Models\EntityVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Función PostgreSQL compartida por tablas append-only (bloques, versiones de documento, entity_versions).
 *
 * Las tablas `template_versions` y el trigger homónimo se eliminaron del dominio; el historial canónico
 * de publicaciones de plantilla es {@see EntityVersion}.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION forbid_append_only_mutation() RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION '% is append-only', TG_TABLE_NAME;
END;
$$ LANGUAGE plpgsql;
SQL);
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS forbid_append_only_mutation();');
        }
    }
};
