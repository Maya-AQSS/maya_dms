<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cambia el pivote user_favorite_templates de template_id (UUID de plantilla)
 * a template_version_id (UUID de entity_versions). El código ya usaba
 * template_version_id; esta migración alinea el esquema con el código.
 *
 * Los favoritos existentes se migran a la versión publicada más reciente
 * de cada plantilla. Si una plantilla no tiene versión publicada, la fila
 * se elimina (el usuario perdería ese favorito, pero es un estado inválido).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Añadir columna nueva nullable para poder hacer el UPDATE antes de poner NOT NULL.
        Schema::table('user_favorite_templates', function (Blueprint $table): void {
            $table->uuid('template_version_id')->nullable()->after('template_id');
        });

        // 2. Popular template_version_id con la versión publicada más reciente.
        DB::update(
            "UPDATE user_favorite_templates uft
             SET template_version_id = (
                 SELECT ev.id
                 FROM entity_versions ev
                 WHERE ev.versionable_type = ?
                   AND ev.versionable_id = uft.template_id
                   AND ev.status = 'published'
                 ORDER BY ev.created_at DESC
                 LIMIT 1
             )",
            ['App\Models\Template'],
        );

        // 3. Eliminar filas sin versión publicada (plantillas sin ninguna publicación).
        DB::delete('DELETE FROM user_favorite_templates WHERE template_version_id IS NULL');

        // 4. Reestructurar tabla: quitar PK+FK antiguas, columna vieja; añadir FK+PK nuevas.
        Schema::table('user_favorite_templates', function (Blueprint $table): void {
            // dropPrimary() sin args en Postgres genera DROP CONSTRAINT {table}_pkey.
            $table->dropPrimary();
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');

            $table->foreign('template_version_id')
                ->references('id')
                ->on('entity_versions')
                ->cascadeOnDelete();

            $table->primary(['user_id', 'template_version_id']);
        });
    }

    public function down(): void
    {
        // Restaurar template_id recuperándolo desde entity_versions.
        Schema::table('user_favorite_templates', function (Blueprint $table): void {
            $table->uuid('template_id')->nullable()->after('user_id');
        });

        DB::update(
            "UPDATE user_favorite_templates uft
             SET template_id = (
                 SELECT ev.versionable_id
                 FROM entity_versions ev
                 WHERE ev.id = uft.template_version_id
             )",
        );

        DB::delete('DELETE FROM user_favorite_templates WHERE template_id IS NULL');

        Schema::table('user_favorite_templates', function (Blueprint $table): void {
            $table->dropPrimary();
            $table->dropForeign(['template_version_id']);
            $table->dropColumn('template_version_id');

            $table->foreign('template_id')
                ->references('id')
                ->on('templates')
                ->cascadeOnDelete();

            $table->primary(['user_id', 'template_id']);
        });
    }
};
