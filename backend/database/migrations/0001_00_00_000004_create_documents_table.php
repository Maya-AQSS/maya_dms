<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ciclo de vida del documento (F-04.3):
     *   draft → in_review → published
     *   published → draft  (si se rechaza, F-06.4)
     *
     * La columna organization_id + el Global Scope garantizan
     * que ningún usuario vea documentos de otra organización (IDOR, F-01.4).
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('templates')->restrictOnDelete();
            $table->string('title');
            $table->string('organization_id');   // FK lógica → organización (FDW/contexto JWT)
            $table->string('study_id')->nullable();
            $table->string('created_by');        // FK lógica → users (FDW)
            $table->string('owner_id');          // puede diferir de created_by tras delegación (F-05.3)
            $table->string('status')->default('draft'); // draft | in_review | published
            $table->integer('current_version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['study_id', 'status']);
            $table->index('created_by');
            $table->index('owner_id');
        });

        Schema::create('document_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('user_id');           // FK lógica → users (FDW)
            $table->string('permission')->default('read'); // read | edit
            $table->string('granted_by');
            $table->timestamps();

            $table->unique(['document_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_shares');
        Schema::dropIfExists('documents');
    }
};
