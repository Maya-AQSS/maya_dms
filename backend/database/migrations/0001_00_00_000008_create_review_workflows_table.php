<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flujo de revisión con N validadores (F-06.1).
     *
     * document_reviews registra cada instancia de revisión de un documento.
     * Un documento en estado in_review tiene exactamente un review activo.
     * La SoD policy garantiza que created_by != reviewer_id (F-01.3).
     */
    public function up(): void
    {
        Schema::create('document_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('reviewer_id');       // FK lógica → users (FDW)
            $table->integer('stage');            // etapa del flujo (1, 2, 3...)
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'stage', 'status']);
            $table->index('reviewer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_reviews');
    }
};
