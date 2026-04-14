<?php

use App\Enums\TemplateVisibilityLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas normativas: datos base + visibilidad, plazo y claves de jerarquía.
 *
 * Los valores de visibility_level coinciden con {@see TemplateVisibilityLevel}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();

            $table->enum('visibility_level', TemplateVisibilityLevel::values())
                ->default(TemplateVisibilityLevel::Personal->value);

            $table->timestamp('delivery_deadline')->nullable();

            $table->string('study_id')->nullable();       // jerarquía académica (FDW)
            $table->string('study_type_id')->nullable();
            $table->string('module_id')->nullable();

            $table->foreignUuid('group_id')
                ->nullable()
                ->constrained('groups')
                ->nullOnDelete();

            $table->string('organization_id')->nullable(); // FK lógica → organización
            $table->string('created_by');                  // FK lógica → users (FDW)
            $table->string('status')->default('draft');    // draft | published | archived
            $table->integer('version')->default(1);

            // Configuración del flujo de revisión
            $table->integer('review_stages')->default(0);  // 0 = sin revisión
            $table->string('review_mode')->default('sequential'); // sequential | parallel

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['organization_id', 'visibility_level', 'status'],
                'templates_org_visibility_status_index'
            );
            $table->index('study_id');
            $table->index('study_type_id');
            $table->index('module_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
