<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade module_id a documents para permitir el filtro de tercer nivel
 * de la jerarquía académica (F-02.3): Tipo → Estudio → Módulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('module_id')->nullable()->after('study_id');
            $table->index(['module_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['module_id', 'status']);
            $table->dropColumn('module_id');
        });
    }
};
