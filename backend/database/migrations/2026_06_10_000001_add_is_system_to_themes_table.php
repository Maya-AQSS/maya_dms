<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca un theme como "de sistema": siempre presente (lo siembra
 * {@see \Database\Seeders\DefaultThemeSeeder}), no borrable, pero editable
 * y clonable por administradores. Sirve de base por defecto sin estar
 * hardcodeado en el render — vive como registro real en `themes`.
 *
 * Delta idempotente vía hasColumn: el create de la tabla ya está aplicado en
 * entornos existentes, así que la columna se añade en una migración aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('themes') || Schema::hasColumn('themes', 'is_system')) {
            return;
        }

        Schema::table('themes', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->index()->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('themes') || ! Schema::hasColumn('themes', 'is_system')) {
            return;
        }

        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
