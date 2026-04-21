<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Ejecuta los seeders de la aplicación.
     */
    public function run(): void
    {
        // Users vienen de un origen externo (FDW en local/prod).
        // Este seeder solo prepara catálogo mock para local/testing.
        $this->call([
            UsersSourceSeeder::class,
            PermissionsSeeder::class,
            UserPermissionsSeeder::class,
            AcademicHierarchySeeder::class,
            UserHierarchySeeder::class,
            TeamsSeeder::class,
            TemplatesSeeder::class,
            TemplateReviewersSeeder::class,
            TemplateBlocksSeeder::class,
            TemplateVersionsSeeder::class,
            DocumentsSeeder::class,
            DocumentBlocksSeeder::class,
            CommentsSeeder::class,
        ]);
    }
}
