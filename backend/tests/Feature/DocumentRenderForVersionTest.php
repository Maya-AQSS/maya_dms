<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\Process;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Services\Contracts\DocumentRenderServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `renderHtmlForVersion` debe renderizar el contenido CONGELADO del snapshot
 * (no el HEAD vivo), manteniendo la estructura/tema de la plantilla viva.
 * Bloques sin entrada en el snapshot caen a su `default_content` (p. ej. bloques
 * añadidos a la plantilla tras la publicación).
 */
final class DocumentRenderForVersionTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_render_for_version_uses_snapshot_content_and_falls_back_to_default(): void
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
            'status' => 'draft',
            'visibility_level' => 'personal',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        // Bloque con override en el snapshot.
        $frozenTplId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id' => $frozenTplId,
            'template_id' => $templateId,
            'block_type' => 'content',
            'title' => 'Bloque congelado',
            'default_content' => $this->tiptapDoc('DEFAULT_FROZEN'),
            'block_state' => 'editable',
            'sort_order' => 1,
        ]);

        // Bloque NUEVO añadido tras publicar: no aparece en el snapshot.
        $newerTplId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id' => $newerTplId,
            'template_id' => $templateId,
            'block_type' => 'content',
            'title' => 'Bloque nuevo',
            'default_content' => $this->tiptapDoc('DEFAULT_NUEVO'),
            'block_state' => 'editable',
            'sort_order' => 2,
        ]);

        $docId = (string) Str::uuid();
        Document::query()->forceCreate([
            'id' => $docId,
            'created_by' => $userId,
            'owner_id' => $userId,
            'template_id' => $templateId,
            'process_id' => $processId,
            'title' => 'Doc',
            'status' => 'draft',
        ]);

        // Contenido VIVO del HEAD: distinto del snapshot, para probar que NO se usa.
        DocumentBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $docId,
            'template_block_id' => $frozenTplId,
            'content' => $this->bareNodes('LIVE_HEAD_CONTENT'),
            'is_filled' => true,
            'sort_order' => 1,
        ]);
        DocumentBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $docId,
            'template_block_id' => $newerTplId,
            'content' => null,
            'is_filled' => false,
            'sort_order' => 2,
        ]);

        // Snapshot: solo congela el primer bloque (con texto distinto al HEAD vivo).
        $snapshotBlocks = [
            [
                'template_block_id' => $frozenTplId,
                'content' => $this->bareNodes('FROZEN_SNAPSHOT_CONTENT'),
            ],
        ];

        $html = app(DocumentRenderServiceInterface::class)->renderHtmlForVersion($docId, $snapshotBlocks);

        // El contenido congelado del snapshot debe salir.
        $this->assertStringContainsString('FROZEN_SNAPSHOT_CONTENT', $html);
        // El contenido del HEAD vivo NO debe aparecer (lo sobreescribe el snapshot).
        $this->assertStringNotContainsString('LIVE_HEAD_CONTENT', $html);
        // Bloque ausente del snapshot → cae a su default_content de plantilla.
        $this->assertStringContainsString('DEFAULT_NUEVO', $html);
    }
}
