<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivote usuario ↔ versión de plantilla favorita.
 *
 * Cambio: de template_id → template_version_id (FK a entity_versions).
 * `user_id`: FK lógica al catálogo de usuarios (FDW / mock), sin FK física.
 *
 * FK a entity_versions se añade en create_entity_versions (0001_00_00_000020).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_favorite_templates', function (Blueprint $table): void {
            $table->string('user_id');
            $table->uuid('template_version_id');
            $table->timestamps();

            $table->primary(['user_id', 'template_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favorite_templates');
    }
};
