<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Process;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Services\Contracts\TemplateRenderServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsTemplatePublicationAnchor;
use Tests\TestCase;

/**
 * `TemplateRenderService::renderHtmlForVersion` debe renderizar el contenido
 * CONGELADO del snapshot (no el default_content vivo), manteniendo la estructura
 * de la plantilla viva. Bloques sin entrada en el snapshot caen a su
 * `default_content` de la plantilla actual, igual que hace DocumentRenderForVersionTest.
 */
final class TemplateRenderForVersionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTemplatePublicationAnchor;

    /** @return list<array<string, mixed>> */
    private function bareNodes(string $text): array
    {
        return [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
        ];
    }

    /** @return array<string, mixed> */
    private function tiptapDoc(string $text): array
    {
        return ['type' => 'doc', 'content' => $this->bareNodes($text)];
    }

    public function test_render_for_version_uses_snapshot_blocks_and_falls_back_to_default_content(): void
    {
        $userId = (string) Str::uuid();

        $processId = (string) Str::uuid();
        Process::query()->forceCreate([
            'id' => $processId,
            'code' => 'PRC'.substr($processId, 0, 4),
            'name' => 'Proc',
            'alias' => 'proc',
        ]);

        $templateId = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => $processId,
            'name' => 'Tpl',
            'created_by' => $userId,
            'status' => 'published',
            'visibility_level' => 'personal',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        // Bloque cuyo contenido está congelado en el snapshot.
        $frozenBlockId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id' => $frozenBlockId,
            'template_id' => $templateId,
            'block_type' => 'content',
            'title' => 'Bloque congelado',
            'default_content' => $this->tiptapDoc('DEFAULT_FROZEN_LIVE'),
            'block_state' => 'editable',
            'sort_order' => 1,
        ]);

        // Bloque añadido tras la publicación: ausente del snapshot.
        $newBlockId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id' => $newBlockId,
            'template_id' => $templateId,
            'block_type' => 'content',
            'title' => 'Bloque nuevo',
            'default_content' => $this->tiptapDoc('DEFAULT_NUEVO_LIVE'),
            'block_state' => 'editable',
            'sort_order' => 2,
        ]);

        // Snapshot de v1: solo congela el primer bloque con texto distinto al default vivo.
        $snapshotBlocks = [
            [
                'id' => $frozenBlockId,
                'block_type' => 'content',
                'title' => 'Bloque congelado',
                'default_content' => $this->tiptapDoc('FROZEN_SNAPSHOT_CONTENT'),
                'sort_order' => 1,
            ],
        ];

        $result = $this->seedCanonicalPublicationForTemplate(
            $templateId, 1, $userId, $snapshotBlocks,
        );
        $entityVersionId = $result['entity_version_id'];

        /** @var TemplateRenderServiceInterface $renderer */
        $renderer = app(TemplateRenderServiceInterface::class);
        $html = $renderer->renderHtmlForVersion($templateId, $entityVersionId);

        // Contenido del snapshot debe aparecer.
        $this->assertStringContainsString('FROZEN_SNAPSHOT_CONTENT', $html);
        // El default_content VIVO del bloque no debe aparecer (sobreescrito por snapshot).
        $this->assertStringNotContainsString('DEFAULT_FROZEN_LIVE', $html);
        // Bloque ausente del snapshot → cae al default_content VIVO de la plantilla.
        $this->assertStringContainsString('DEFAULT_NUEVO_LIVE', $html);
    }
}
