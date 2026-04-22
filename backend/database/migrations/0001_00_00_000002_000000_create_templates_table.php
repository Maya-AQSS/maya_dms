<?php

use App\Enums\TemplateVisibilityLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas normativas: datos base + visibilidad, plazo y claves de jerarquía.
 *
 * Los valores de visibility_level coinciden con {@see TemplateVisibilityLevel}.
 *
 * `team_id` guarda el id del equipo en el catálogo lógico `teams`.
 * No hay FK física: en entornos con FDW `teams` es una vista; en `testing` es tabla (validación con exists:teams,id).
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

            // Sin FK física hacia `teams` (vista FDW o tabla en testing); validación exists:teams,id en Form Requests.
            $table->uuid('team_id')->nullable()->index();

            $table->string('created_by');                  // FK lógica → users (FDW)
            $table->string('status')->default('draft');    // draft | published | archived
            $table->integer('version')->default(1);

            // Configuración del flujo de revisión
            $table->integer('review_stages')->default(0);  // 0 = sin revisión
            $table->string('review_mode')->default('parallel'); // sequential | parallel

            $table->timestamps();
            $table->softDeletes();

            $table->index(['visibility_level', 'status'], 'templates_visibility_status_index');
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
