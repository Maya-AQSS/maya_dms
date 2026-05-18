<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivote usuario ↔ plantilla favorita (entidad lógica `templates`).
 *
 * `user_id`: FK lógica al catálogo de usuarios (FDW / mock), sin FK física.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_favorite_templates', function (Blueprint $table): void {
            $table->string('user_id');
            $table->foreignUuid('template_id')->constrained('templates')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favorite_templates');
    }
};
