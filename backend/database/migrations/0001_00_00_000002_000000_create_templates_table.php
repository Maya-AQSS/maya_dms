<?php

use App\Enums\TemplateVisibilityLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ancla de plantilla en un proceso (`process_id`). Identidad estable del recurso en catálogo y FKs desde documentos.
 *
 * Dominio (modelo objetivo): nombre, descripción, {@see TemplateVisibilityLevel}, plazos, jerarquía académica,
 * equipo, estado del ciclo (draft / in_review / published / archived), configuración del flujo de revisión y la
 * autoría de esa **copia de trabajo** pertenecen al agregado **versión** ({@see \App\Models\EntityVersion}), no a la
 * plantilla como concepto abstracto.
 *
 * Implementación actual: el borrador editable y el estado hasta publicar siguen persistiendo en esta tabla para no
 * duplicar todavía una fila {@code entity_versions} “cabezal” por plantilla; cada **publicación** canónica sí se
 * guarda en {@code entity_versions} con snapshot inmutable (changelog, {@code published_at}, {@code published_by}, …).
 * Trasladar también el borrador a {@code entity_versions} es un refactor amplio (repositorios, scopes, políticas, tests).
 *
 * `team_id`: catálogo lógico `teams`; sin FK física (FDW vista / testing tabla).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('process_id')->constrained('processes')->restrictOnDelete();
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

            // Configuración del flujo de revisión
            $table->integer('review_stages')->default(0);  // 0 = sin revisión
            $table->string('review_mode')->default('parallel'); // sequential | parallel

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['process_id', 'id']);
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
