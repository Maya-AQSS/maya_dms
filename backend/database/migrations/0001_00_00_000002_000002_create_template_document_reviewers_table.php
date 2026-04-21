<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pool de posibles validadores de documento por plantilla.
 *
 * Define quiénes pueden ser elegidos como validadores de los documentos generados
 * a partir de esta plantilla. Es una lista plana (sin stage ni estado): el orden
 * y el estado se asignan en `document_reviews` cuando se crea el documento.
 *
 * PK compuesta (template_id, user_id): no hay identidad propia para cada fila;
 * la combinación plantilla+usuario es la clave natural.
 *
 * Diferencia con `template_reviewers`: esa tabla gestiona quiénes revisan la
 * plantilla normativa. Esta tabla gestiona quiénes pueden validar los documentos
 * que se generen a partir de ella.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_document_reviewers', function (Blueprint $table) {
            $table->foreignUuid('template_id')->constrained('templates')->cascadeOnDelete();
            $table->uuid('template_version_id')->nullable();
            $table->string('user_id'); // FK lógica → users (FDW)
            $table->timestamps();

            $table->primary(['template_id', 'user_id']);
            $table->index('template_version_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_document_reviewers');
    }
};
