<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Solo seeders sobre tablas físicas propias de DMS.
     * Identidad (users, teams, jerarquía académica) y permisos provienen
     * de FDW (authorization + Odoo) — read-only, no se seedean aquí.
     */
    public function run(): void
    {
        $this->call([
            ProcessesSeeder::class,
            TemplatesSeeder::class,
            TemplateReviewersSeeder::class,
            TemplateDocumentReviewersSeeder::class,
            TemplateBlocksSeeder::class,
            TemplateVersionsSeeder::class,
            DocumentsSeeder::class,
            EntityVersionsSeeder::class,
            UserFavoritesSeeder::class,
            DocumentBlocksSeeder::class,
            DocumentReviewsSeeder::class,
            CommentsSeeder::class,
        ]);
    }
}
