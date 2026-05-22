<?php

namespace Tests\Unit\Support;

use App\Support\AcademicHierarchyTreeBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AcademicHierarchyTreeBuilderTest extends TestCase
{
    public function test_builds_nested_tree_from_context_arrays(): void
    {
        if (! Schema::hasTable('course_modules')) {
            $this->markTestSkipped('Tablas de jerarquía académica no disponibles en este entorno.');
        }

        DB::table('study_types')->insertOrIgnore(['id' => 'ST_ESO', 'name' => 'ESO']);
        DB::table('studies')->insertOrIgnore(['id' => 'S_ESO_1', 'study_type_id' => 'ST_ESO', 'name' => '1º ESO']);
        DB::table('course_modules')->insertOrIgnore([
            'id' => 'M_MAT_1',
            'study_id' => 'S_ESO_1',
            'name' => 'Matemáticas',
        ]);

        $builder = new AcademicHierarchyTreeBuilder();

        $tree = $builder->build([
            'study_types' => [
                ['id' => 'ST_ESO', 'code' => 'ST_ESO', 'name' => 'Educación Secundaria Obligatoria'],
            ],
            'studies' => [
                [
                    'id' => 'S_ESO_1',
                    'code' => 'S_ESO_1',
                    'name' => '1º ESO',
                    'study_type_id' => 'ST_ESO',
                ],
            ],
            'modules' => [
                ['id' => 'M_MAT_1', 'code' => 'M_MAT_1', 'name' => 'Matemáticas'],
            ],
        ]);

        $this->assertCount(1, $tree);
        $this->assertSame('ST_ESO', $tree[0]['id']);
        $this->assertSame('S_ESO_1', $tree[0]['studies'][0]['id']);
        $this->assertSame('M_MAT_1', $tree[0]['studies'][0]['course_modules'][0]['id']);
        $this->assertSame('S_ESO_1', $tree[0]['studies'][0]['course_modules'][0]['study_id']);
    }

    public function test_study_type_name_comes_from_catalog_not_raw_id(): void
    {
        if (! Schema::hasTable('course_modules')) {
            $this->markTestSkipped('Tablas de jerarquía académica no disponibles en este entorno.');
        }

        DB::table('study_types')->insertOrIgnore(['id' => 'GS', 'name' => 'Grado Superior']);

        $builder = new AcademicHierarchyTreeBuilder();

        $tree = $builder->build([
            'study_types' => [
                ['id' => 'GS', 'code' => 'GS', 'name' => 'GS'],
            ],
            'studies' => [],
            'modules' => [],
        ]);

        $this->assertSame('Grado Superior', $tree[0]['name']);
    }

    public function test_hydrates_study_from_catalog_when_only_module_is_assigned(): void
    {
        if (! Schema::hasTable('course_modules')) {
            $this->markTestSkipped('Tablas de jerarquía académica no disponibles en este entorno.');
        }

        DB::table('study_types')->insertOrIgnore(['id' => 'ST_ESO', 'name' => 'ESO']);
        DB::table('studies')->insertOrIgnore(['id' => 'S_ESO_1', 'study_type_id' => 'ST_ESO', 'name' => '1º ESO']);
        DB::table('course_modules')->insertOrIgnore([
            'id' => 'M_MAT_1',
            'study_id' => 'S_ESO_1',
            'name' => 'Matemáticas',
        ]);

        $builder = new AcademicHierarchyTreeBuilder();

        $tree = $builder->build([
            'study_types' => [],
            'studies' => [],
            'modules' => [
                ['id' => 'M_MAT_1', 'code' => 'M_MAT_1', 'name' => 'Matemáticas'],
            ],
        ]);

        $this->assertCount(1, $tree);
        $this->assertSame('ST_ESO', $tree[0]['id']);
        $this->assertSame('S_ESO_1', $tree[0]['studies'][0]['id']);
        $this->assertCount(1, $tree[0]['studies'][0]['course_modules']);
    }
}
