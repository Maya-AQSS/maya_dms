<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flujo de revisión con N validadores.
     *
     * document_reviews guarda una fila por revisor asignado (columna stage) al pasar el documento
     * a in_review. Puede haber varias filas pending a la vez: según templates.review_mode,
     * parallel permite que cualquier revisión pendiente reciba approve/reject; sequential
     * restringe en aplicación a la etapa pendiente de número más bajo (puede haber varios
     * revisores en la misma etapa).
     *
     * La segregación de funciones (SoD: el autor no actúa como revisor) se aplica en políticas y servicio.
     *
     * UNIQUE(document_id, reviewer_id): alineado con template_reviewers (un mismo usuario no repite
     * como revisor de la plantilla); evita filas duplicadas si los datos de plantilla estuvieran corruptos.
     */
    public function up(): void
    {
        Schema::create('document_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('reviewer_id');       // FK lógica → users (FDW)
            $table->integer('stage');            // etapa del flujo (1, 2, 3...)
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'reviewer_id'], 'document_reviews_document_id_reviewer_id_unique');
            $table->index(['document_id', 'stage', 'status']);
            $table->index('reviewer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_reviews');
    }
};
