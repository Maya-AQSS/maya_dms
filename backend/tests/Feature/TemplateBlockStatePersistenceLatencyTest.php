<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\TemplateBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TemplateBlockStatePersistenceLatencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_block_state_update_persists_under_200ms_on_average(): void
    {
        $templateId = (string) Str::uuid();
        $blockId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla latencia',
            'description' => null,
            'visibility_level' => 'personal',
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => (string) Str::uuid(),
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $blockId,
            'template_id' => $templateId,
            'type' => 'paragraph',
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 0,
        ]);

        $iterations = 30;
        $states = ['modifiable', 'locked', 'editable'];
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            DB::table('template_blocks')
                ->where('id', $blockId)
                ->update([
                    'block_state' => $states[$i % 3],
                    'updated_at' => now(),
                ]);
        }

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $avgMs = $elapsedMs / $iterations;

        $this->assertLessThan(
            200.0,
            $avgMs,
            "La persistencia promedio del cambio de estado tardó {$avgMs} ms (límite: 200 ms).",
        );
    }
}

