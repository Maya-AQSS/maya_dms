<?php

use App\Support\PostgresFdwMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Jerarquía académica (tipo de enseñanza → estudios → módulos) como catálogo externo.
 *
 * Rutas:
 * - Entorno `testing`: tablas reales `study_types`, `studies`, `course_modules` (p. ej. SQLite en tests).
 * - Entorno `local`: tablas `*_source` en la misma BD, FDW + vistas homónimas; reutiliza el servidor
 *   {@see self::FDW_SERVER} creado en la migración de usuarios.
 * - Otros entornos: FDW hacia tablas o vistas remotas (`config/database.fdw.*`).
 *
 * Datos de ejemplo en local: {@see \Database\Seeders\AcademicHierarchySeeder}.
 */
return new class extends Migration
{
    /**
     * Servidor FDW creado en la migración de usuarios; aquí solo se declaran foreign tables adicionales.
     */
    private const FDW_SERVER = 'users_server';

    /**
     * Vistas consumidas por la aplicación (mismo nombre que el catálogo lógico).
     *
     * @var list<string>
     */
    private const CATALOG_VIEWS = ['study_types', 'studies', 'course_modules'];

    public function up(): void
    {
        if (app()->environment('testing', 'local')) {
            $this->createTestingCatalogTables();
            return;
        }

        $this->setupFdw();
    }

    public function down(): void
    {
        if (app()->environment('testing', 'local')) {
            DB::statement('DROP TABLE IF EXISTS course_modules');
            DB::statement('DROP TABLE IF EXISTS studies');
            DB::statement('DROP TABLE IF EXISTS study_types');
            return;
        }

        foreach (self::CATALOG_VIEWS as $table) {
            PostgresFdwMigration::dropViewOrTableInPublic($table);
            PostgresFdwMigration::dropForeignTableIfExists($table.'_fdw');
        }

        if (app()->environment('local')) {
            DB::statement('DROP TABLE IF EXISTS course_modules_source CASCADE');
            DB::statement('DROP TABLE IF EXISTS studies_source CASCADE');
            DB::statement('DROP TABLE IF EXISTS study_types_source CASCADE');
        }
    }

    /**
     * Catálogo mínimo para tests (sin postgres_fdw).
     */
    private function createTestingCatalogTables(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS study_types (
                id VARCHAR(255) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');

        DB::statement('
            CREATE TABLE IF NOT EXISTS studies (
                id VARCHAR(255) PRIMARY KEY,
                study_type_id VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (study_type_id) REFERENCES study_types(id)
            )
        ');

        DB::statement('
            CREATE TABLE IF NOT EXISTS course_modules (
                id VARCHAR(255) PRIMARY KEY,
                study_id VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (study_id) REFERENCES studies(id)
            )
        ');
    }

    /**
     * Configura FDW para local y producción.
     * - local: apunta a tablas source (misma BD)
     * - producción: apunta a tablas remotas (config database.fdw.*)
     */
    private function setupFdw(): void
    {
        $isLocal = app()->environment('local');

        if ($isLocal) {
            $this->createLocalSourceTables();

            $schema = 'public';
            $tables = [
                'study_types' => 'study_types_source',
                'studies' => 'studies_source',
                'course_modules' => 'course_modules_source',
            ];
        } else {
            $schema = config('database.fdw.users.schema', 'public');
            $tables = [
                'study_types' => config('database.fdw.study_types.table', 'v_study_types'),
                'studies' => config('database.fdw.studies.table', 'v_studies'),
                'course_modules' => config('database.fdw.course_modules.table', 'v_course_modules'),
            ];
        }

        foreach ($tables as $viewName => $sourceTable) {
            if ($viewName === 'study_types') {
                $columns = 'id VARCHAR(255), name VARCHAR(255)';
                $viewSelect = 'id, name';
            } elseif ($viewName === 'studies') {
                $columns = 'id VARCHAR(255), study_type_id VARCHAR(255), name VARCHAR(255)';
                $viewSelect = 'id, study_type_id, name';
            } else {
                $columns = 'id VARCHAR(255), study_id VARCHAR(255), name VARCHAR(255)';
                $viewSelect = 'id, study_id, name';
            }

            PostgresFdwMigration::createForeignTableWithPassThroughView(
                catalogBaseName: $viewName,
                foreignColumnsSql: $columns,
                viewSelectSql: $viewSelect,
                fdwServer: self::FDW_SERVER,
                remoteSchema: $schema,
                remoteRelationName: $sourceTable,
            );
        }
    }

    private function createLocalSourceTables(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS study_types_source (
                id VARCHAR(255) PRIMARY KEY,
                name VARCHAR(255) NOT NULL
            )
        ');

        DB::statement('
            CREATE TABLE IF NOT EXISTS studies_source (
                id VARCHAR(255) PRIMARY KEY,
                study_type_id VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                FOREIGN KEY (study_type_id) REFERENCES study_types_source(id)
            )
        ');

        DB::statement('
            CREATE TABLE IF NOT EXISTS course_modules_source (
                id VARCHAR(255) PRIMARY KEY,
                study_id VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                FOREIGN KEY (study_id) REFERENCES studies_source(id)
            )
        ');
    }
};
