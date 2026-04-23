<?php

namespace Database\Seeders;

use App\Services\Contracts\UserProfileServiceInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Puebla las tablas de asignación usuario ↔ jerarquía académica con datos mock.
 *
 * En entorno local escribe en las tablas `_source` (leídas vía FDW por la vista).
 * En entorno testing escribe directamente en las tablas físicas.
 *
 * @see database/data/user_hierarchy_mock.php
 */
class UserHierarchySeeder extends Seeder
{
    public function run(): void
    {
        $data = $this->mockAssignments();

        if ($data === []) {
            return;
        }

        $now = Carbon::now();

        $this->seedTable(
            source: 'user_study_types_source',
            fallback: 'user_study_types',
            rows: $data['user_study_types'] ?? [],
            columns: ['user_id', 'study_type_id'],
            now: $now,
        );

        $this->seedTable(
            source: 'user_studies_source',
            fallback: 'user_studies',
            rows: $data['user_studies'] ?? [],
            columns: ['user_id', 'study_id'],
            now: $now,
        );

        $this->seedTable(
            source: 'user_course_modules_source',
            fallback: 'user_course_modules',
            rows: $data['user_course_modules'] ?? [],
            columns: ['user_id', 'module_id'],
            now: $now,
        );

        $this->invalidateCaches($data);
    }

    /**
     * Inserta filas en la tabla de origen correcta para el entorno actual.
     *
     * @param  list<array<string, string>>  $rows
     * @param  list<string>  $columns
     */
    private function seedTable(
        string $source,
        string $fallback,
        array $rows,
        array $columns,
        Carbon $now,
    ): void {
        if ($rows === []) {
            return;
        }

        $table = $this->writableTable($source, $fallback);

        if ($table === null) {
            return;
        }

        $mapped = array_map(static function (array $row) use ($columns, $now): array {
            $entry = ['id' => (string) Str::uuid()];
            foreach ($columns as $col) {
                $entry[$col] = $row[$col];
            }
            $entry['created_at'] = $now;
            $entry['updated_at'] = $now;

            return $entry;
        }, $rows);

        DB::table($table)->insertOrIgnore($mapped);
    }

    /**
     * Devuelve el nombre de la tabla escribible según el entorno.
     * - local   → tabla `_source`
     * - testing → tabla física directa
     */
    private function writableTable(string $source, string $fallback): ?string
    {
        if (Schema::hasTable($source)) {
            return $source;
        }

        if (app()->environment('testing') && Schema::hasTable($fallback)) {
            return $fallback;
        }

        return null;
    }

    /**
     * Invalida la caché de perfil para todos los usuarios afectados.
     *
     * @param  array<string, list<array<string, string>>>  $data
     */
    private function invalidateCaches(array $data): void
    {
        $userIds = [];

        foreach ($data as $assignments) {
            foreach ($assignments as $row) {
                if (isset($row['user_id'])) {
                    $userIds[$row['user_id']] = true;
                }
            }
        }

        $service = app(UserProfileServiceInterface::class);

        foreach (array_keys($userIds) as $userId) {
            $service->invalidateCache($userId);
        }
    }

    /**
     * @return array<string, list<array<string, string>>>
     */
    private function mockAssignments(): array
    {
        $filePath = database_path('data/user_hierarchy_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $data = require $filePath;

        return is_array($data) ? $data : [];
    }
}
