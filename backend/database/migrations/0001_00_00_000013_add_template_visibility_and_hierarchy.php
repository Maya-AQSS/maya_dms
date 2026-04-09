<?php

use App\Enums\TemplateVisibilityLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas normativas: nivel de visibilidad, plazo de entrega opcional
 * y claves de jerarquía académica (FDW / grupos locales).
 *
 * Los valores de visibility_level coinciden con {@see TemplateVisibilityLevel}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->enum('visibility_level', TemplateVisibilityLevel::values())
                ->default(TemplateVisibilityLevel::Personal->value)
                ->after('description');

            $table->timestamp('delivery_deadline')->nullable()->after('visibility_level');

            $table->string('study_type_id')->nullable()->after('study_id');
            $table->string('module_id')->nullable()->after('study_type_id');

            $table->foreignUuid('group_id')
                ->nullable()
                ->after('module_id')
                ->constrained('groups')
                ->nullOnDelete();

            $table->index(
                ['organization_id', 'visibility_level', 'status'],
                'templates_org_visibility_status_index'
            );
            $table->index('study_type_id');
            $table->index('module_id');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropIndex('templates_org_visibility_status_index');
            $table->dropIndex(['study_type_id']);
            $table->dropIndex(['module_id']);

            $table->dropColumn([
                'visibility_level',
                'delivery_deadline',
                'study_type_id',
                'module_id',
                'group_id',
            ]);
        });
    }
};
