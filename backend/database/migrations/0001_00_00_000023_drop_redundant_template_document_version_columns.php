<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versión publicada de plantilla: {@see entity_versions.version_number}.
 * Versión de documento publicada y fechas de ciclo: {@see entity_versions} + {@see document_versions} / revisión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            if (Schema::hasColumn('templates', 'version')) {
                $table->dropColumn('version');
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            $drop = array_values(array_filter(
                ['current_version', 'submitted_at', 'published_at'],
                static fn (string $c): bool => Schema::hasColumn('documents', $c),
            ));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            if (! Schema::hasColumn('templates', 'version')) {
                $table->integer('version')->default(1);
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'current_version')) {
                $table->integer('current_version')->default(1);
            }
            if (! Schema::hasColumn('documents', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable();
            }
            if (! Schema::hasColumn('documents', 'published_at')) {
                $table->timestamp('published_at')->nullable();
            }
        });
    }
};
