<?php

declare(strict_types=1);

namespace Tests\Feature\TemplateBlocks;

use App\Enums\BlockKind;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateVersionBlockLayer;
use App\Services\TemplateVersionBlockLayerResolver;
use App\Services\TemplateVersionBlockLayerWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifica que el campo `kind` viaja correctamente en el
 * `override_payload` del pivote `template_version_block_layers` y
 * sobrevive a la reconstrucción del snapshot. Se ejerce el writer y
 * el resolver directamente — el flujo HTTP/Auth está cubierto por
 * los tests existentes del controlador, no es objeto de este test.
 */
class KindPersistsInVersioningTest extends TestCase
{
    use RefreshDatabase;

    private function makeTemplate(): Template
    {
        $pid = (string) Str::uuid();
        DB::table('processes')->insert([
            'id' => $pid,
            'code' => 'TEST'.substr($pid, 0, 6),
            'name' => 'Proceso de test',
            'alias' => 'test',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tid = (string) Str::uuid();
        DB::table('templates')->insert([
            'id' => $tid,
            'process_id' => $pid,
            'head_entity_version_id' => null,
            'theme_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Recuperamos por DB raw para evitar los global scopes del modelo
        // (join_head_entity_version + user_access exigen una EV publicada,
        // que no necesitamos para ejercer el writer).
        $template = new Template;
        $template->setRawAttributes(['id' => $tid], true);
        $template->exists = true;

        return $template;
    }

    /**
     * @param  array<int, array{kind: string, title: string}>  $blocks
     * @return list<string>  IDs de bloque creados, en orden.
     */
    private function makeBlocks(string $templateId, array $blocks): array
    {
        $ids = [];
        foreach ($blocks as $idx => $spec) {
            $bid = (string) Str::uuid();
            TemplateBlock::query()->forceCreate([
                'id' => $bid,
                'template_id' => $templateId,
                'title' => $spec['title'],
                'default_content' => null,
                'description' => null,
                'block_state' => $spec['kind'] === BlockKind::Toc->value ? 'locked' : 'editable',
                'kind' => $spec['kind'],
                'sort_order' => $idx,
            ]);
            $ids[] = $bid;
        }

        return $ids;
    }

    private function makePublishedVersion(string $templateId, int $number = 1): EntityVersion
    {
        return EntityVersion::query()->create([
            'id' => (string) Str::uuid(),
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => $number,
            'status' => 'published',
            'created_by' => 'test-user',
            'published_by' => 'test-user',
            'published_at' => now(),
            'is_snapshot_immutable' => true,
        ]);
    }

    public function test_kind_is_serialized_into_override_payload_for_each_block(): void
    {
        $template = $this->makeTemplate();
        $tid = (string) $template->id;
        $ids = $this->makeBlocks($tid, [
            ['kind' => BlockKind::Cover->value, 'title' => 'Portada'],
            ['kind' => BlockKind::Content->value, 'title' => 'Contenido'],
            ['kind' => BlockKind::Blank->value, 'title' => 'Blank'],
            ['kind' => BlockKind::Toc->value, 'title' => 'Índice'],
        ]);
        $version = $this->makePublishedVersion($tid);

        /** @var TemplateVersionBlockLayerWriter $writer */
        $writer = app(TemplateVersionBlockLayerWriter::class);
        $writer->syncLayersForNewPublication($version, $template);

        $expectedKinds = [
            BlockKind::Cover->value,
            BlockKind::Content->value,
            BlockKind::Blank->value,
            BlockKind::Toc->value,
        ];

        foreach ($ids as $idx => $blockId) {
            $layer = TemplateVersionBlockLayer::query()
                ->where('entity_version_id', $version->id)
                ->where('template_block_id', $blockId)
                ->first();

            $this->assertNotNull($layer, "Layer missing for block {$blockId}");
            $payload = is_array($layer->override_payload) ? $layer->override_payload : [];
            $this->assertArrayHasKey('kind', $payload, "kind missing in payload for block {$blockId}");
            $this->assertSame($expectedKinds[$idx], $payload['kind']);
        }
    }

    public function test_resolver_returns_kind_when_reconstructing_snapshot(): void
    {
        $template = $this->makeTemplate();
        $tid = (string) $template->id;
        $ids = $this->makeBlocks($tid, [
            ['kind' => BlockKind::Cover->value, 'title' => 'Portada'],
            ['kind' => BlockKind::Toc->value, 'title' => 'Índice'],
            ['kind' => BlockKind::Content->value, 'title' => 'Contenido'],
        ]);
        $version = $this->makePublishedVersion($tid);

        /** @var TemplateVersionBlockLayerWriter $writer */
        $writer = app(TemplateVersionBlockLayerWriter::class);
        $writer->syncLayersForNewPublication($version, $template);

        /** @var TemplateVersionBlockLayerResolver $resolver */
        $resolver = app(TemplateVersionBlockLayerResolver::class);
        $resolved = $resolver->resolveBlocksSnapshot((string) $version->id);

        $this->assertCount(3, $resolved);

        // El resolver devuelve los bloques en el orden de las layers (sort_order).
        $expectedKinds = [BlockKind::Cover->value, BlockKind::Toc->value, BlockKind::Content->value];
        foreach ($resolved as $idx => $row) {
            $this->assertIsArray($row);
            $this->assertArrayHasKey('kind', $row, "kind missing in resolved block at index {$idx}");
            $this->assertSame($expectedKinds[$idx], $row['kind']);
            $this->assertSame($ids[$idx], $row['id']);
        }
    }
}
