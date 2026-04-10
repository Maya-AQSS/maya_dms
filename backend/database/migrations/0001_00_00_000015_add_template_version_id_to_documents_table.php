<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ancla el documento a la versión publicada de plantilla usada al crearlo (F-03.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignUuid('template_version_id')
                ->nullable()
                ->after('template_id')
                ->constrained('template_versions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['template_version_id']);
            $table->dropColumn('template_version_id');
        });
    }
};
