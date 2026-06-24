<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\ApplyTemplateMigrationDto;
use App\DTOs\Documents\DocumentMigrationPayloadDto;
use App\DTOs\Documents\TemplateVersionStatusDto;
use App\Models\Document;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Repositories\Contracts\DocumentBlockRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * DMS-B07 (cluster B): migración/versionado de plantilla de un documento —
 * estado de versión, payload de migración y aplicación in-situ del upgrade
 * (re-anclaje + reconciliación de bloques). Extraído de DocumentService.
 *
 * `applyTemplateMigration` devuelve el Model (no el DTO): el wrapping a
 * DocumentDto + `beforeMap` se queda en DocumentService, dueño de `toDto`.
 */
class DocumentMigrationService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly DocumentBlockRepositoryInterface $documentBlockRepository,
        private readonly DocumentBlockService $documentBlockService,
        private readonly DocumentMigrationPayloadResolver $migrationPayloadResolver,
    ) {}

    public function templateVersionStatus(string $documentId): TemplateVersionStatusDto
    {
        $document = $this->documentRepository->findOrFail($documentId);

        $currentFull = $this->resolveCurrentPublishedTemplateVersionMeta($document);
        $latestFull = $this->resolveLatestPublishedTemplateVersionMeta((string) $document->template_id);

        $current = $currentFull !== null
            ? [
                'id' => $currentFull['id'],
                'version_number' => $currentFull['version_number'],
            ]
            : null;

        $hasUpdate = $currentFull !== null
            && $latestFull !== null
            && $latestFull['version_number'] > $currentFull['version_number'];

        return new TemplateVersionStatusDto(
            currentVersion: $current,
            latestVersion: $latestFull,
            hasUpdate: $hasUpdate,
            changelog: $hasUpdate ? $latestFull['changelog'] : null,
        );
    }

    public function migrationPayload(string $sourceDocumentId): DocumentMigrationPayloadDto
    {
        return $this->migrationPayloadResolver->resolve($sourceDocumentId);
    }

    /**
     * Aplica la migración de plantilla in-situ (re-ancla + reconcilia bloques) y
     * devuelve el Model refrescado. El wrapping a DTO lo hace DocumentService.
     */
    public function applyTemplateMigration(ApplyTemplateMigrationDto $dto): Document
    {
        return $this->documentRepository->transaction(function () use ($dto): Document {
            $document = $this->documentRepository->findOrFail($dto->documentId);

            if ($document->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => [__('validation.document.migrate_state')],
                ]);
            }

            $target = $this->entityVersionRepository->findPublishedByIdForVersionable(
                $dto->targetTemplateVersionId,
                Template::class,
                (string) $document->template_id,
            );
            if ($target === null) {
                throw ValidationException::withMessages([
                    'target_template_version_id' => [__('validation.migrate.target_invalid')],
                ]);
            }

            $current = $this->resolveCurrentPublishedTemplateVersionMeta($document);
            if ($current !== null && (int) $target->version_number <= (int) $current['version_number']) {
                throw ValidationException::withMessages([
                    'target_template_version_id' => [__('validation.migrate.target_older')],
                ]);
            }

            $targetDefinitions = $this->documentBlockService
                ->templatePublicationDefinitionRowsFromEntityVersion((string) $target->id);
            if ($targetDefinitions === []) {
                throw ValidationException::withMessages([
                    'target_template_version_id' => [__('validation.migrate.target_no_blocks')],
                ]);
            }

            $this->reconcileDocumentBlocks(
                (string) $document->id,
                $targetDefinitions,
                $dto->migratedBlockContent,
                $dto->removedBlockActions,
                $dto->actorId,
            );

            // Re-anclar tras reconciliar (la columna no es atributo delegado del head).
            $this->documentRepository->updateTemplateVersionAnchor(
                (string) $document->id,
                (string) $target->id,
            );

            return $this->documentRepository->findOrFailForRefreshAfterMutation($dto->documentId);
        });
    }

    /**
     * Reconcilia los bloques del documento con las definiciones de la versión destino:
     * crea los nuevos, aplica contenido migrado (salvo locked) en los existentes, y
     * elimina/mantiene los removidos según {@code $removedActions}.
     *
     * @param  list<array<string, mixed>>  $targetDefinitions
     * @param  array<string, mixed>  $migrated
     * @param  array<string, string>  $removedActions
     */
    private function reconcileDocumentBlocks(
        string $documentId,
        array $targetDefinitions,
        array $migrated,
        array $removedActions,
        string $actorId,
    ): void {
        $existing = $this->documentBlockRepository->listByDocumentKeyedByTemplateBlock($documentId);
        $targetIds = [];

        foreach ($targetDefinitions as $def) {
            $templateBlockId = (string) ($def['id'] ?? '');
            if ($templateBlockId === '') {
                continue;
            }
            $targetIds[$templateBlockId] = true;

            $state = (string) ($def['block_state'] ?? 'editable');
            $row = $existing->get($templateBlockId);
            $hasMigrated = $state !== 'locked' && array_key_exists($templateBlockId, $migrated);

            if ($row !== null) {
                if ($hasMigrated) {
                    $this->documentBlockRepository->updateBlock($row, $migrated[$templateBlockId], true, $actorId);
                }

                continue;
            }

            // Bloque nuevo en la versión destino: lo instanciamos.
            $content = $state === 'editable' ? null : ($def['default_content'] ?? null);
            if ($hasMigrated) {
                $content = $migrated[$templateBlockId];
            }

            $this->documentBlockRepository->insertDocumentBlock([
                'document_id' => $documentId,
                'template_block_id' => $templateBlockId,
                'content' => $content,
                'sort_order' => (int) ($def['sort_order'] ?? 0),
                'is_filled' => $content !== null,
                'last_edited_by' => $content !== null ? $actorId : null,
            ]);
        }

        foreach ($existing as $templateBlockId => $row) {
            if (isset($targetIds[(string) $templateBlockId])) {
                continue;
            }
            // Removido en la versión destino: eliminar o mantener según elección.
            if (($removedActions[(string) $templateBlockId] ?? null) === 'delete') {
                $this->documentBlockRepository->deleteBlock($row);
            }
        }
    }

    /**
     * Última versión publicada de plantilla ({@see EntityVersion}).
     *
     * @return array{id: string, version_number: int, changelog: string}|null
     */
    private function resolveLatestPublishedTemplateVersionMeta(string $templateId): ?array
    {
        return $this->entityVersionRepository->findLatestPublishedMetaForVersionable(Template::class, $templateId);
    }

    /**
     * Meta de la publicación anclada ({@see EntityVersion}, columna `template_version_id`).
     *
     * @return array{id: string, version_number: int, changelog: string}|null
     */
    private function resolveCurrentPublishedTemplateVersionMeta(Document $document): ?array
    {
        $versionId = $document->template_version_id;

        if (! is_string($versionId) || $versionId === '') {
            return null;
        }

        $entity = $this->entityVersionRepository->findPublishedMetaByIdForVersionable(
            $versionId,
            Template::class,
            (string) $document->template_id,
        );
        if ($entity !== null) {
            return $entity;
        }

        $ev = $this->entityVersionRepository->findPublishedByIdAndType($versionId, Template::class);

        if ($ev === null) {
            return null;
        }

        return [
            'id' => (string) $ev->id,
            'version_number' => (int) $ev->version_number,
            'changelog' => (string) ($ev->changelog ?? ''),
        ];
    }
}
