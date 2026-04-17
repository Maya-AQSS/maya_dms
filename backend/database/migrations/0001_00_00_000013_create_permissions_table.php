<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo local de permisos de aplicación.
 *
 * La clave primaria es `code` (estable para políticas, seeds y FK desde asignaciones).
 * No usa FDW: el corporativo puede seguir exponiendo solo filas de asignación vía FDW.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->string('code', 191)->primary();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
