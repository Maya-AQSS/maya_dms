<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshots inmutables del documento completo (F-04.4).
     * Se crean en eventos clave: submit_for_review, publish, reject.
     * snapshot_data contiene el JSON completo del documento (todos sus bloques)
     * para poder reconstruir cualquier versión pasada sin joins.
     */
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->integer('version_number');
            $table->string('trigger_event');     // submitted | published | rejected
            $table->string('triggered_by');      // FK lógica → users (FDW)
            $table->jsonb('snapshot_data');      // snapshot completo del documento
            $table->text('notes')->nullable();   // motivo de rechazo u observaciones
            $table->timestamp('created_at');

            $table->unique(['document_id', 'version_number']);
            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
