<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DidacticProgrammingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $template = \App\Models\Template::withoutGlobalScopes()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => 'Plantilla Base de Programación',
            'organization_id' => 'ORG_001',
            'created_by' => 'USER_001',
            'status' => 'published',
            'version' => 1
        ]);

        $docs = [
            ['title' => 'Prog. Matemáticas 1º ESO', 'study_id' => 'S_ESO_1', 'course_module_id' => 'M_MAT_1', 'status' => 'published'],
            ['title' => 'Prog. Inglés 1º ESO', 'study_id' => 'S_ESO_1', 'course_module_id' => 'M_ENG_1', 'status' => 'draft'],
            ['title' => 'Prog. Física 1º Bach', 'study_id' => 'S_BACH_1_C', 'course_module_id' => 'M_FIS_1C', 'status' => 'in_review'],
            ['title' => 'Prog. DWECL DAW', 'study_id' => 'S_FP_DAW', 'course_module_id' => 'M_DAW_DWECL', 'status' => 'published'],
            ['title' => 'Prog. DWES DAW', 'study_id' => 'S_FP_DAW', 'course_module_id' => 'M_DAW_DWES', 'status' => 'published'],
        ];

        foreach ($docs as $doc) {
            \App\Models\Document::withoutGlobalScopes()->create(array_merge($doc, [
                'id' => \Illuminate\Support\Str::uuid(),
                'template_id' => $template->id,
                'organization_id' => 'ORG_001',
                'created_by' => 'USER_001',
                'owner_id' => 'USER_001',
            ]));
        }
    }
}
