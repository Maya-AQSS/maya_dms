<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rellena las tablas fuente locales de jerarquía académica (solo si existen tras la migración FDW local).
 */
class AcademicHierarchySeeder extends Seeder
{
    public function run(): void
    {
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
        $studies    = $loaded['studies_source'] ?? [];
        $modules    = $loaded['course_modules_source'] ?? [];

        // Tablas *_source: presentes en entorno local con FDW (o futura producción).
        if (Schema::hasTable('study_types_source')) {
            if ($studyTypes !== []) {
                DB::table('study_types_source')->insertOrIgnore($studyTypes);
            }
            if ($studies !== []) {
                DB::table('studies_source')->insertOrIgnore($studies);
            }
            if ($modules !== []) {
                DB::table('course_modules_source')->insertOrIgnore($modules);
            }
        }

        // Tablas directas: presentes en entorno local/testing sin FDW.
        // La migración las crea vacías; hay que poblarlas aquí.
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

        // Invalidar caché de jerarquía para forzar recarga al siguiente request.
        Cache::store('redis')->forget('academic_hierarchy_tree');
    }
}
