<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            $this->createLocalTables();
            return;
        }

        $this->setupFdw();
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            DB::statement('DROP TABLE IF EXISTS course_modules');
            DB::statement('DROP TABLE IF EXISTS studies');
            DB::statement('DROP TABLE IF EXISTS study_types');
            return;
        }

        foreach (['course_modules', 'studies', 'study_types'] as $table) {
            DB::statement("
                DO \$\$ BEGIN
                    IF EXISTS (
                        SELECT 1 FROM information_schema.views
                        WHERE table_schema = 'public' AND table_name = '{$table}'
                    ) THEN
                        DROP VIEW {$table} CASCADE;
                    ELSIF EXISTS (
                        SELECT 1 FROM information_schema.tables
                        WHERE table_schema = 'public' AND table_name = '{$table}'
                        AND table_type = 'BASE TABLE'
                    ) THEN
                        DROP TABLE {$table} CASCADE;
                    END IF;
                END \$\$
            ");

            DB::statement("DROP FOREIGN TABLE IF EXISTS {$table}_fdw CASCADE");
        }

        if (app()->environment('local')) {
            DB::statement('DROP TABLE IF EXISTS course_modules_source CASCADE');
            DB::statement('DROP TABLE IF EXISTS studies_source CASCADE');
            DB::statement('DROP TABLE IF EXISTS study_types_source CASCADE');
        }
    }

    private function createLocalTables(): void
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

    private function setupFdw(): void
    {
        $isLocal = app()->environment('local');

        if ($isLocal) {
            $this->createLocalSourceTables();
            $this->seedLocalSourceTables();

            $schema = 'public';
            $tables = [
                'study_types' => 'study_types_source',
                'studies' => 'studies_source',
                'course_modules' => 'course_modules_source'
            ];
        } else {
            $schema = config('database.fdw.users.schema', 'public');
            $tables = [
                'study_types' => config('database.fdw.study_types.table', 'v_study_types'),
                'studies' => config('database.fdw.studies.table', 'v_studies'),
                'course_modules' => config('database.fdw.course_modules.table', 'v_course_modules')
            ];
        }

        // The FDW server (users_server) should already be created by users migration
        // We reuse it here

        foreach ($tables as $viewName => $sourceTable) {
            $safeSchema = addcslashes($schema, "'\\");
            $safeSourceTable = addcslashes($sourceTable, "'\\");
            
            // Generate FDW table definition conditionally
            if ($viewName === 'study_types') {
                $columns = "id VARCHAR(255), name VARCHAR(255)";
                $viewSelect = "id, name";
            } elseif ($viewName === 'studies') {
                $columns = "id VARCHAR(255), study_type_id VARCHAR(255), name VARCHAR(255)";
                $viewSelect = "id, study_type_id, name";
            } else { // course_modules
                $columns = "id VARCHAR(255), study_id VARCHAR(255), name VARCHAR(255)";
                $viewSelect = "id, study_id, name";
            }

            DB::statement("
                CREATE FOREIGN TABLE IF NOT EXISTS {$viewName}_fdw (
                    {$columns}
                )
                SERVER users_server
                OPTIONS (schema_name '{$safeSchema}', table_name '{$safeSourceTable}')
            ");

            DB::statement("
                CREATE OR REPLACE VIEW {$viewName} AS
                SELECT {$viewSelect}
                FROM {$viewName}_fdw
            ");
            
            $this->revokeWritePermissions("{$viewName}_fdw");
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
    
    private function seedLocalSourceTables(): void 
    {
        // Add sample data so local developers have hierarchy data
        DB::table('study_types_source')->insertOrIgnore([
            ['id' => 'ST_ESO', 'name' => 'Educación Secundaria Obligatoria'],
            ['id' => 'ST_BACH', 'name' => 'Bachillerato'],
            ['id' => 'ST_FP', 'name' => 'Formación Profesional']
        ]);
        
        DB::table('studies_source')->insertOrIgnore([
            ['id' => 'S_ESO_1', 'study_type_id' => 'ST_ESO', 'name' => '1º ESO'],
            ['id' => 'S_BACH_1_C', 'study_type_id' => 'ST_BACH', 'name' => '1º Bachillerato Ciencias'],
            ['id' => 'S_FP_DAW', 'study_type_id' => 'ST_FP', 'name' => 'CFGS Desarrollo de Aplicaciones Web']
        ]);
        
        DB::table('course_modules_source')->insertOrIgnore([
            ['id' => 'M_MAT_1', 'study_id' => 'S_ESO_1', 'name' => 'Matemáticas'],
            ['id' => 'M_ENG_1', 'study_id' => 'S_ESO_1', 'name' => 'Inglés'],
            ['id' => 'M_FIS_1C', 'study_id' => 'S_BACH_1_C', 'name' => 'Física y Química'],
            ['id' => 'M_DAW_DWECL', 'study_id' => 'S_FP_DAW', 'name' => 'Desarrollo Web en Entorno Cliente'],
            ['id' => 'M_DAW_DWES', 'study_id' => 'S_FP_DAW', 'name' => 'Desarrollo Web en Entorno Servidor']
        ]);
    }

    private function revokeWritePermissions(string $tableName): void
    {
        $appUser = config('database.connections.pgsql.username');

        if (empty($appUser)) {
            return;
        }

        try {
            DB::statement("REVOKE INSERT, UPDATE, DELETE ON {$tableName} FROM \"{$appUser}\"");
            DB::statement("GRANT SELECT ON {$tableName} TO \"{$appUser}\"");
        } catch (\Throwable $e) {
            logger()->warning("FDW: could not set permissions for {$appUser} on {$tableName}: {$e->getMessage()}");
        }
    }
};
