<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Process;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Services\Contracts\TemplateRenderServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresión: el PDF/preview de una PLANTILLA solo mostraba los bloques
 * estructurales (portada, índice, hoja en blanco), no los de contenido.
 *
 * `TemplateRenderService` llamaba `TiptapHtmlRenderer::renderDoc()` directamente,
 * que exige `type === 'doc'` y devuelve '' ante una LISTA PELADA de nodos. Los
 * bloques estructurales no pasan por ese renderer, así que sobrevivían — de ahí
 * el síntoma. Verificamos que el contenido se renderice en ambas formas.
 */
final class TemplateRenderContentBlocksTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function tiptapDoc(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
            ],
        ];
    }

    /** @return list<array<string, mixed>> Lista pelada, como guarda el editor. */
    private function bareNodes(string $text): array
    {
        return [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
        ];
    }

    public function test_template_content_blocks_render_for_both_content_shapes(): void
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

        // Bloque de contenido con default_content como documento {type:doc}.
        TemplateBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $templateId,
            'block_type' => 'content',
            'title' => 'Bloque doc',
            'default_content' => $this->tiptapDoc('TEXTO_FORMA_DOC'),
            'block_state' => 'editable',
            'sort_order' => 1,
        ]);

        // Bloque de contenido con default_content como LISTA PELADA — el caso del bug.
        TemplateBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $templateId,
            'block_type' => 'content',
            'title' => 'Bloque lista',
            'default_content' => $this->bareNodes('TEXTO_LISTA_PELADA'),
            'block_state' => 'editable',
            'sort_order' => 2,
        ]);

        $html = app(TemplateRenderServiceInterface::class)->renderHtml($templateId, false);

        $this->assertStringContainsString('TEXTO_FORMA_DOC', $html);
        // Esto es lo que el bug rompía: la lista pelada salía vacía.
        $this->assertStringContainsString('TEXTO_LISTA_PELADA', $html);
    }
}
