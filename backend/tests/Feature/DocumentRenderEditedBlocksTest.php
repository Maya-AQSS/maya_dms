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
 * Regresión de bug: los bloques EDITADOS por el redactor no aparecían en el PDF.
 *
 * El editor persiste `document_blocks.content` como LISTA PELADA de nodos
 * (`[ {...} ]`), mientras que `template_blocks.default_content` se guarda como
 * documento completo (`{ type:'doc', content:[...] }`). El renderer del PDF sólo
 * sabía renderizar la segunda forma, así que los bloques con contenido propio
 * (editados) salían vacíos y los no editados (que caen al default de plantilla)
 * sí se veían. Verificamos que AMBAS formas se rendericen.
 */
final class DocumentRenderEditedBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function tiptapDoc(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
            ],
        ];
    }

    /** Lista pelada de nodos, como la guarda el editor al editar un bloque. */
    private function bareNodes(string $text): array
    {
        return [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
        ];
    }

    public function test_edited_bare_list_block_content_renders_in_pdf_html(): void
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

        // Bloque EDITADO: el redactor cambió el texto → content es lista pelada.
        $editedTplId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id' => $editedTplId,
            'template_id' => $templateId,
            'block_type' => 'content',
            'title' => 'Bloque editado',
            'default_content' => $this->tiptapDoc('TEXTO_PLANTILLA_EDITADO'),
            'block_state' => 'editable',
            'sort_order' => 1,
        ]);

        // Bloque SIN editar: document_block sin content → cae al default de plantilla.
        $untouchedTplId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id' => $untouchedTplId,
            'template_id' => $templateId,
            'block_type' => 'content',
            'title' => 'Bloque sin tocar',
            'default_content' => $this->tiptapDoc('TEXTO_POR_DEFECTO'),
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

        DocumentBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $docId,
            'template_block_id' => $editedTplId,
            'content' => $this->bareNodes('TEXTO_REDACTOR'),
            'is_filled' => true,
            'sort_order' => 1,
        ]);

        DocumentBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $docId,
            'template_block_id' => $untouchedTplId,
            'content' => null,
            'is_filled' => false,
            'sort_order' => 2,
        ]);

        $html = app(DocumentRenderServiceInterface::class)->renderHtml($docId, false);

        // El texto editado (lista pelada) DEBE aparecer — esto es lo que el bug rompía.
        $this->assertStringContainsString('TEXTO_REDACTOR', $html);
        // El bloque sin tocar sigue cayendo al default de la plantilla.
        $this->assertStringContainsString('TEXTO_POR_DEFECTO', $html);
    }
}
