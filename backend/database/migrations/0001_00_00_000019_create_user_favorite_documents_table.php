<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivote usuario ↔ documento favorito (entidad lógica `documents`).
 *
 * `user_id`: FK lógica al catálogo de usuarios (FDW / mock), sin FK física.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_favorite_documents', function (Blueprint $table): void {
            $table->string('user_id');
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favorite_documents');
    }
};
