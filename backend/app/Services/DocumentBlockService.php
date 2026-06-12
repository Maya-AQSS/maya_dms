<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\BlockDisplayDto;
use App\DTOs\Documents\BlockUpdateDto;
use App\DTOs\Documents\DeleteDocumentBlockDto;
use App\DTOs\Documents\UpdateDocumentBlockDto;
use App\Enums\BlockState;
use App\Enums\BlockType;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Repositories\Contracts\DocumentBlockRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Support\TiptapContentSemantics;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class DocumentBlockService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentBlockRepositoryInterface $documentBlockRepository,
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly TemplateVersionBlockLayerResolver $templateVersionBlockLayerResolver,
    ) {}

    /**
     * Bloques para mostrar/editar: definición según document template_version_id y contenido en document_blocks.
     *
     * @return list<BlockDisplayDto>
     */
    public function blocksForDisplay(string $documentId): array
    {
        $document = $this->documentRepository->findOrFail($documentId);
        $byTemplateBlockId = $this->documentBlockRepository->findBlocksForDocumentWithRelations($documentId);
        $definitions = $this->blockDefinitionsForDocument($document);

        $out = [];
        foreach ($definitions as $def) {
            $tid = (string) $def['id'];
            $row = $byTemplateBlockId->get($tid);
            $state = (string) ($def['block_state'] ?? 'editable');
            // El índice es SIEMPRE modificable (el redactor elige qué secciones
            // entran), aunque la plantilla lo guardara con otro estado.
            if (($def['block_type'] ?? 'content') === 'index') {
                $state = 'modifiable';
            }
            // 'mandatory' is not stored as a separate column; it is fully determined by
            // block_state: only 'editable' blocks are mandatory (must be filled by document creator).
            // 'modifiable' blocks are optional — creator may keep the template default. The snapshot
            // field is absent in older published versions, so never rely on it.
            $mandatory = $state === 'editable';

            // Optional blocks with no document_block row were explicitly removed by the user.
            // Keep them in the response (with is_deleted: true) so the diff view can show them.
            $isDeleted = $state === 'optional' && $row === null;

            $out[] = new BlockDisplayDto(
                document_block_id: $row?->id,
                template_block_id: $tid,
                type: $def['type'] ?? '',
                title: $def['title'] ?? null,
                description: $def['description'] ?? null,
                default_content: $def['default_content'] ?? null,
                block_state: $state,
                mandatory: $mandatory,
                sort_order: (int) ($def['sort_order'] ?? 0),
                content: $row?->content,
                is_filled: (bool) ($row?->is_filled ?? false),
                is_deleted: $isDeleted,
                block_type: (string) ($def['block_type'] ?? 'content'),
                page_break_after: (bool) ($def['page_break_after'] ?? false),
                theme_id: isset($def['theme_id']) && $def['theme_id'] !== null ? (string) $def['theme_id'] : null,
                apply_theme: (bool) ($def['apply_theme'] ?? true),
            );
        }

        // Bloques que ya no están en la versión de plantilla anclada pero siguen en el
        // documento (se mantuvieron al migrar de versión): se anexan para que sigan
        // visibles/editables, marcados como huérfanos para que la UI los señale.
        $definitionIds = [];
        foreach ($definitions as $def) {
            $definitionIds[(string) ($def['id'] ?? '')] = true;
        }
        foreach ($byTemplateBlockId as $tid => $row) {
            $tid = (string) $tid;
            if ($tid === '' || isset($definitionIds[$tid])) {
                continue;
            }
            $tpl = $row->templateBlock;
            $tplState = $tpl?->block_state;
            $state = $tplState instanceof BlockState ? $tplState->value : (string) ($tplState ?? 'optional');

            $out[] = new BlockDisplayDto(
                document_block_id: $row->id,
                template_block_id: $tid,
                type: '',
                title: $tpl?->title,
                description: $tpl?->description,
                default_content: $tpl?->default_content,
                block_state: $state !== '' ? $state : 'optional',
                mandatory: false,
                sort_order: (int) $row->sort_order,
                content: $row->content,
                is_filled: (bool) $row->is_filled,
                is_deleted: false,
                is_orphaned: true,
                block_type: ($tpl?->block_type instanceof BlockType ? $tpl->block_type->value : (string) ($tpl?->block_type ?? 'content')),
                page_break_after: (bool) ($tpl?->page_break_after ?? false),
                theme_id: $tpl?->theme_id !== null ? (string) $tpl?->theme_id : null,
                apply_theme: (bool) ($tpl?->apply_theme ?? true),
            );
        }

        return $out;
    }

    /**
     * Actualiza el contenido de un bloque de documento.
     */
    public function updateBlock(UpdateDocumentBlockDto $dto): BlockUpdateDto
    {
        return $this->documentRepository->transaction(function () use ($dto): BlockUpdateDto {
            $document = $this->documentRepository->findOrFail($dto->documentId);
            if (! in_array($document->status, ['draft', 'rejected'], true)) {
                throw new AuthorizationException('Solo se pueden editar bloques de documentos en borrador o rechazados.');
            }

            $block = $this->documentRepository->findBlockInDocumentOrFail(
                $dto->documentId,
                $dto->documentBlockId,
            );

            $definitions = collect($this->blockDefinitionsForDocument($document))
                ->keyBy(fn (array $def) => (string) $def['id']);
            $definition = $definitions->get((string) $block->template_block_id) ?? [];

            $state = (string) ($definition['block_state'] ?? 'editable');
            $blockType = (string) ($definition['block_type'] ?? 'content');
            // El índice es siempre modificable (coherente con blocksForDisplay).
            if ($blockType === 'index') {
                $state = 'modifiable';
            }

            if ($state === 'locked') {
                throw new AuthorizationException('Este bloque está bloqueado y no admite edición.');
            }

            // Portada e índice guardan una CONFIG (objeto `{kind:...}`), no cuerpo
            // tiptap. La comparación semántica tiptap los normaliza a vacío y los
            // trataría como "sin cambios" (no se guardaría nunca). Para ellos
            // comparamos el contenido crudo canónico.
            $isStructuralFill = $blockType === 'cover' || $blockType === 'index';
            $contentUnchanged = $isStructuralFill
                ? $this->jsonEncodeCanonical($block->content) === $this->jsonEncodeCanonical($dto->content)
                : $this->documentBlockContentEquals($block->content, $dto->content);

            if ($contentUnchanged) {
                return new BlockUpdateDto(
                    documentBlockId: (string) $block->id,
                    templateBlockId: (string) $block->template_block_id,
                    content: $block->content,
                    isFilled: (bool) $block->is_filled,
                    lastEditedBy: (string) $block->last_edited_by,
                    updatedAt: $block->updated_at?->toIso8601String(),
                );
            }

            $this->appendModifiableBlockVersionSnapshotsIfNeeded($document, $block, $definition, $dto);

            $isFilled = $isStructuralFill
                ? ($dto->content !== null && $dto->content !== [])
                : $this->isContentFilled($dto->content);
            if (! $isStructuralFill && $state === 'editable') {
                $default = $definition['default_content'] ?? null;
                if ($this->documentBlockContentEquals($dto->content, $default)) {
                    $isFilled = false;
                }
            }
            $this->documentBlockRepository->updateBlock($block, $dto->content, $isFilled, $dto->actorId);

            return new BlockUpdateDto(
                documentBlockId: (string) $block->id,
                templateBlockId: (string) $block->template_block_id,
                content: $block->content,
                isFilled: $isFilled,
                lastEditedBy: $dto->actorId,
                updatedAt: $block->updated_at?->toIso8601String(),
            );
        });
    }

    public function assertMandatoryBlocksAreFilled(string $documentId): void
    {
        $document = $this->documentRepository->findOrFail($documentId);
        // Solo bloques de CONTENIDO editables son obligatorios de rellenar con
        // texto. Los estructurales (portada/índice/blanco) no se miden como tiptap.
        $definitions = collect($this->blockDefinitionsForDocument($document))
            ->filter(fn (array $def): bool => ($def['block_state'] ?? '') === 'editable'
                && ! in_array($def['block_type'] ?? 'content', ['cover', 'index', 'blank'], true));

        if ($definitions->isEmpty()) {
            return;
        }

        $blocksByTemplateBlockId = $this->documentBlockRepository->findBlocksForDocumentWithRelations($documentId);
        $missing = [];

        foreach ($definitions as $definition) {
            $templateBlockId = (string) ($definition['id'] ?? '');
            if ($templateBlockId === '') {
                continue;
            }

            $block = $blocksByTemplateBlockId->get($templateBlockId);
            if ($block === null) {
                $missing[] = $templateBlockId;

                continue;
            }

            $default = $definition['default_content'] ?? null;
            $stillPlaceholder = $this->documentBlockContentEquals($block->content, $default);

            if ($stillPlaceholder || ! $this->isContentFilled($block->content)) {
                $missing[] = $templateBlockId;
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'blocks' => ['Debes completar todos los bloques editables antes de enviar a revisión.'],
                'missing_template_block_ids' => $missing,
            ]);
        }
    }

    /**
     * Valida que todos los bloques modificables tengan contenido diferente al predeterminado de la plantilla.
     * Debe llamarse después de {@see assertMandatoryBlocksAreFilled} (los bloques vacíos ya se detectaron allí).
     */
    public function assertModifiableBlocksAreModified(string $documentId): void
    {
        $document = $this->documentRepository->findOrFail($documentId);
        $definitions = collect($this->blockDefinitionsForDocument($document))
            ->filter(fn (array $def): bool => ($def['block_state'] ?? '') === 'modifiable');

        if ($definitions->isEmpty()) {
            return;
        }

        $blocksByTemplateBlockId = $this->documentBlockRepository->findBlocksForDocumentWithRelations($documentId);
        $unmodified = [];

        foreach ($definitions as $def) {
            $templateBlockId = (string) ($def['id'] ?? '');
            if ($templateBlockId === '') {
                continue;
            }

            $block = $blocksByTemplateBlockId->get($templateBlockId);
            if ($block === null) {
                continue;
            }

            if ($this->documentBlockContentEquals($block->content, $def['default_content'] ?? null)) {
                $unmodified[] = [
                    'id' => $templateBlockId,
                    'title' => (string) ($def['title'] ?? ''),
                ];
            }
        }

        if ($unmodified !== []) {
            throw ValidationException::withMessages([
                'blocks' => ['Debes editar todos los bloques modificables antes de enviar a revisión.'],
                'unmodified_modifiable_block_ids' => array_column($unmodified, 'id'),
                'unmodified_modifiable_block_titles' => array_column($unmodified, 'title'),
            ]);
        }
    }

    private function blockDefinitionsForDocument(Document $document): array
    {
        if ($document->template_version_id === null) {
            return $this->blockDefinitionsFromLiveTemplate((string) $document->template_id);
        }

        $this->documentRepository->loadTemplateVersion($document);

        $ev = $document->templateVersion;
        if ($ev === null) {
            $ev = $this->entityVersionRepository->findPublishedByIdForVersionable(
                (string) $document->template_version_id,
                Template::class,
                (string) $document->template_id,
            );
        }

        if ($ev !== null) {
            $rows = $this->sortedBlocksFromEntitySnapshot($ev->snapshot_data);
            if ($rows === []) {
                $resolved = $this->templateVersionBlockLayerResolver->resolveBlocksSnapshot((string) $ev->id);
                $rows = $this->sortedSnapshotBlocks($resolved);
            }
            if ($rows !== []) {
                return $rows;
            }

            $retryEntity = $this->entityVersionRepository->findPublishedForEntityVersionNumber(
                Template::class,
                (string) $document->template_id,
                (int) $ev->version_number,
            );
            if ($retryEntity !== null) {
                $rows = $this->sortedBlocksFromEntitySnapshot($retryEntity->snapshot_data);
                if ($rows === []) {
                    $resolved = $this->templateVersionBlockLayerResolver->resolveBlocksSnapshot((string) $retryEntity->id);
                    $rows = $this->sortedSnapshotBlocks($resolved);
                }
                if ($rows !== []) {
                    return $rows;
                }
            }
        }

        return $this->blockDefinitionsFromLiveTemplate((string) $document->template_id);
    }

    /**
     * Filas de definición de bloques desde el snapshot publicado de plantilla ({@see EntityVersion}).
     *
     * @return list<array<string, mixed>>
     */
    public function templatePublicationDefinitionRowsFromEntityVersion(string $entityVersionId): array
    {
        $entityVersion = $this->entityVersionRepository->findOrFail($entityVersionId);
        $rows = $this->sortedBlocksFromEntitySnapshot($entityVersion->snapshot_data);
        if ($rows !== []) {
            return $rows;
        }

        $resolved = $this->templateVersionBlockLayerResolver->resolveBlocksSnapshot((string) $entityVersion->id);

        return $this->sortedSnapshotBlocks($resolved);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blockDefinitionsFromLiveTemplate(string $templateId): array
    {
        $template = $this->templateRepository->findOrFailWithBlocksOrderedWithoutCatalogScope($templateId);

        return $template->blocks->map(fn ($b) => [
            'id' => $b->id,
            'type' => $b->type,
            'block_type' => $b->block_type instanceof BlockType ? $b->block_type->value : (string) ($b->block_type ?? 'content'),
            'title' => $b->title,
            'description' => $b->description,
            'default_content' => $b->default_content,
            'block_state' => $b->block_state,
            'mandatory' => $b->mandatory,
            'page_break_after' => (bool) $b->page_break_after,
            'theme_id' => $b->theme_id !== null ? (string) $b->theme_id : null,
            'apply_theme' => (bool) $b->apply_theme,
            'sort_order' => $b->sort_order,
        ])->all();
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function sortedSnapshotBlocks(array $blocks): array
    {
        if ($blocks === []) {
            return [];
        }

        return collect($blocks)
            ->sortBy(fn ($b) => $b['sort_order'] ?? 0)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sortedBlocksFromEntitySnapshot(mixed $snapshotData): array
    {
        if (! is_array($snapshotData)) {
            return [];
        }

        $blocks = $snapshotData['blocks'] ?? null;

        return is_array($blocks) ? $this->sortedSnapshotBlocks($blocks) : [];
    }

    private function documentBlockContentEquals(mixed $a, mixed $b): bool
    {
        return $this->jsonEncodeCanonical(TiptapContentSemantics::normalizeContentArray($a))
            === $this->jsonEncodeCanonical(TiptapContentSemantics::normalizeContentArray($b));
    }

    private function jsonEncodeCanonical(mixed $value): string
    {
        try {
            $normalized = $this->normalizeKeysForCanonicalJson($value);

            return json_encode(
                $normalized,
                \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (\JsonException) {
            return '';
        }
    }

    private function normalizeKeysForCanonicalJson(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value === [] || array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeKeysForCanonicalJson($item), $value);
        }

        ksort($value);

        foreach ($value as $k => $nested) {
            $value[$k] = $this->normalizeKeysForCanonicalJson($nested);
        }

        return $value;
    }

    private function isContentFilled(mixed $content): bool
    {
        return TiptapContentSemantics::isContentFilled($content);
    }

    private function appendModifiableBlockVersionSnapshotsIfNeeded(
        Document $document,
        DocumentBlock $block,
        array $definition,
        UpdateDocumentBlockDto $dto,
    ): void {
        if (($definition['block_state'] ?? 'editable') !== 'modifiable') {
            return;
        }

        $blockId = (string) $block->id;
        $max = $this->documentRepository->maxBlockVersionNumberForDocumentBlock($blockId);

        if ($max === 0) {
            $baseline = $this->normalizeBlockVersionPayload($definition['default_content'] ?? null);
            $baselineEditor = (string) ($document->created_by ?? $document->owner_id ?? $dto->actorId);
            $this->documentRepository->insertDocumentBlockVersion(
                $blockId,
                $dto->documentId,
                1,
                $baseline,
                null,
                $baselineEditor,
            );
            $max = 1;
        }

        $this->documentRepository->insertDocumentBlockVersion(
            $blockId,
            $dto->documentId,
            $max + 1,
            $this->normalizeBlockVersionPayload($dto->content),
            null,
            $dto->actorId,
        );
    }

    /**
     * Removes an optional document block row, permanently hiding it from the document.
     *
     * Only optional blocks (block_state === 'optional') may be deleted this way.
     * The document must still be in draft status.
     */
    public function deleteOptionalBlock(DeleteDocumentBlockDto $dto): void
    {
        $this->documentRepository->transaction(function () use ($dto) {
            $document = $this->documentRepository->findOrFail($dto->documentId);

            if (! in_array($document->status, ['draft', 'rejected'], true)) {
                throw new AuthorizationException('Solo se pueden editar bloques de documentos en borrador o rechazados.');
            }

            $block = $this->documentRepository->findBlockInDocumentOrFail(
                $dto->documentId,
                $dto->documentBlockId,
            );

            $definitions = collect($this->blockDefinitionsForDocument($document))
                ->keyBy(fn (array $def) => (string) $def['id']);
            $definition = $definitions->get((string) $block->template_block_id) ?? [];
            $state = (string) ($definition['block_state'] ?? 'editable');
            // 'mandatory' has no dedicated column — optionality is determined solely by block_state.
            if ($state !== 'optional') {
                throw new AuthorizationException('Solo se pueden eliminar bloques opcionales.');
            }

            $this->documentBlockRepository->deleteBlock($block);
        });
    }

    private function normalizeBlockVersionPayload(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [];
    }
}
