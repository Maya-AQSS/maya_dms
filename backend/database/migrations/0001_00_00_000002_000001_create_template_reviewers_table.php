<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisores por plantilla y etapa del flujo de revisión.
 *
 * Un mismo usuario no puede repetirse como revisor de la misma plantilla.
 * El stage ordena el flujo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_reviewers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('templates')->cascadeOnDelete();
            $table->uuid('template_version_id')->nullable();
            $table->string('user_id');    // FK lógica → users (FDW)
            $table->integer('stage');     // orden de revisión (1, 2, 3...)
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['template_id', 'user_id'], 'template_reviewers_template_id_user_id_unique');
            $table->index('template_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_reviewers');
    }
};
