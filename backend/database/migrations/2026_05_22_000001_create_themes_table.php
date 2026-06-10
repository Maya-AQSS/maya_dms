<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Themes: identidad visual reutilizable y clonable que una plantilla puede aplicar
 * a sus documentos. Modela paleta, tipografía, layout (regiones drag-and-drop),
 * assets (logo, fondo, watermark) y metadatos de accesibilidad PDF/UA.
 *
 * Decisión: por simplicidad de iteración los Themes NO usan el patrón
 * entity-version snapshot (a diferencia de templates/documents). Si en el futuro
 * se exige historial inmutable de themes, refactorizar a EntityVersion.
 *
 * Usa $table->json() (cross-driver: Postgres `json`, SQLite `text`). El
 * StoreThemeRequest aplica defaults a nivel aplicación — no en BD — para
 * que los tests con SQLite funcionen sin issues con DEFAULT en columnas JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft'); // draft | published | archived

            // Ownership / scope. team_id sin FK física (catálogo lógico).
            $table->string('created_by'); // FDW lógico a users
            $table->uuid('team_id')->nullable()->index();

            // ─── Diseño visual ────────────────────────────────────
            // palette: tokens nombrados — primary, secondary, accent, text, background.
            $table->json('palette')->nullable();

            // typography: heading_font, body_font (stacks), base_size_pt, line_height.
            $table->json('typography')->nullable();

            // layout: regions (header/footer/sidebar/content_slots) serializado por Puck.
            $table->json('layout')->nullable();

            // accessibility: metadatos para WeasyPrint --pdf-variant pdf/ua-1.
            $table->json('accessibility')->nullable();

            // Origen del clon (auditoría): theme padre del que se derivó.
            $table->uuid('cloned_from_id')->nullable();

            // is_system: marca un theme como de sistema (siempre presente, no borrable).
            $table->boolean('is_system')->default(false)->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['created_by']);
            $table->index('cloned_from_id');
        });

        /* Añade FK desde templates.theme_id a themes.id */
        if (Schema::hasTable('templates')) {
            Schema::table('templates', function (Blueprint $table) {
                $table->foreign('theme_id')
                    ->references('id')
                    ->on('themes')
                    ->restrictOnDelete();
            });
        }

        /* Añade FK desde template_blocks.theme_id a themes.id (tema por bloque). */
        if (Schema::hasTable('template_blocks')) {
            Schema::table('template_blocks', function (Blueprint $table) {
                $table->index('theme_id');
                $table->foreign('theme_id')
                    ->references('id')
                    ->on('themes')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('template_blocks')) {
            Schema::table('template_blocks', function (Blueprint $table) {
                try {
                    $table->dropForeign(['theme_id']);
                } catch (Throwable) {
                }
                try {
                    $table->dropIndex(['theme_id']);
                } catch (Throwable) {
                }
            });
        }

        if (Schema::hasTable('templates')) {
            Schema::table('templates', function (Blueprint $table) {
                try {
                    $table->dropForeign(['theme_id']);
                } catch (Throwable) {
                }
            });
        }

        Schema::dropIfExists('themes');
    }
};
