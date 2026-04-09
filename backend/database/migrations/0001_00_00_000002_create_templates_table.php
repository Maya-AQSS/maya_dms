<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('study_id')->nullable();       // jerarquía académica (FDW)
            $table->string('organization_id')->nullable(); // FK lógica → organización
            $table->string('created_by');                  // FK lógica → users (FDW)
            $table->string('status')->default('draft');    // draft | published | archived
            $table->integer('version')->default(1);

            // Configuración del flujo de revisión
            $table->integer('review_stages')->default(0);  // 0 = sin revisión
            $table->string('review_mode')->default('sequential'); // sequential | parallel

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index('study_id');
        });

        Schema::create('template_reviewers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('templates')->cascadeOnDelete();
            $table->string('user_id');    // FK lógica → users (FDW)
            $table->integer('stage');     // orden de revisión (1, 2, 3...)
            $table->timestamps();

            $table->unique(['template_id', 'user_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_reviewers');
        Schema::dropIfExists('templates');
    }
};
