<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Solo seeders sobre tablas físicas propias de DMS.
     * Identidad (users, teams, jerarquía académica) y permisos provienen
     * de FDW (authorization + Odoo) — read-only, no se seedean aquí.
     *
     * Tras reset-all del slot, ejecutar con MAYA_SLOT definido en .env del backend.
     */
    public function run(): void
    {
        $this->assertDevUsersExistInFdw();

        $this->call([
            ProcessesSeeder::class,
            DefaultThemeSeeder::class,
            DemoThemesSeeder::class,
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

    /**
     * Falla pronto si los UUID del pack no existen en la vista FDW (MAYA_SLOT ausente o mal configurado).
     */
    private function assertDevUsersExistInFdw(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if ((int) DB::table('users')->count() < 1) {
            return;
        }

        $devUsers = require database_path('data/maya_dev_users.php');
        $checks = ['direccion', 'docente_i', 'jefe_d_i'];
        $missing = [];

        foreach ($checks as $key) {
            $id = $devUsers[$key] ?? '';
            if ($id === '' || ! DB::table('users')->where('id', $id)->exists()) {
                $missing[] = $key.' ('.$id.')';
            }
        }

        if ($missing === []) {
            return;
        }

        $slot = env('MAYA_SLOT') ?: '(vacío)';

        throw new RuntimeException(
            'Los UUID de maya_dev_users.php no existen en FDW users: '
            .implode(', ', $missing)
            .". MAYA_SLOT actual: {$slot}. Tras reset-all, db:seed debe ejecutarse en el contenedor backend con el .env del slot."
        );
    }
}
