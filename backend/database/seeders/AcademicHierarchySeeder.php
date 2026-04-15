<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rellena las tablas fuente locales de jerarquía académica (solo si existen tras la migración FDW local).
 */
class AcademicHierarchySeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('study_types_source')) {
            return;
        }

        $filePath = database_path('data/academic_hierarchy_mock.php');
        if (! is_file($filePath)) {
            return;
        }

        /** @var mixed $loaded */
        $loaded = require $filePath;
        if (! is_array($loaded)) {
            return;
        }

        $studyTypes = $loaded['study_types_source'] ?? [];
        $studies  = $loaded['studies_source'] ?? [];
        $modules  = $loaded['course_modules_source'] ?? [];

        if ($studyTypes !== []) {
            DB::table('study_types_source')->insertOrIgnore($studyTypes);
        }
        if ($studies !== []) {
            DB::table('studies_source')->insertOrIgnore($studies);
        }
        if ($modules !== []) {
            DB::table('course_modules_source')->insertOrIgnore($modules);
        }

        // En entorno local la migración crea tablas directas (no FDW).
        // Hay que poblarlas también para que la app pueda leer los datos.
        if (Schema::hasTable('study_types') && DB::table('study_types')->count() === 0) {
            if ($studyTypes !== []) {
                DB::table('study_types')->insertOrIgnore($studyTypes);
            }
            if ($studies !== []) {
                DB::table('studies')->insertOrIgnore($studies);
            }
            if ($modules !== []) {
                DB::table('course_modules')->insertOrIgnore($modules);
            }
        }
    }
}
