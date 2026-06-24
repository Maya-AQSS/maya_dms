<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Repositories\Contracts\DocumentBlockRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Services\DocumentBlockService;
use App\Services\DocumentMigrationBlockDiffer;
use App\Services\DocumentMigrationPayloadResolver;
use App\Services\DocumentMigrationService;
use App\Services\DocumentReviewerResolutionService;
use App\Services\DocumentReviewService;
use App\Services\DocumentService;
use App\Services\DocumentShareService;
use App\Services\DocumentStateService;
use App\Services\DocumentVersionService;
use App\Support\DocumentReviewModeResolver;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class DocumentServiceDualReadResolutionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolve_current_template_version_meta_falls_back_to_entity_when_legacy_row_missing(): void
    {
        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $tplRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $snap = Mockery::mock(SnapshotServiceInterface::class);
        $blockSvc = Mockery::mock(DocumentBlockService::class);
        $verSvc = Mockery::mock(DocumentVersionService::class);
        $shareSvc = Mockery::mock(DocumentShareService::class);
        $stateSvc = Mockery::mock(DocumentStateService::class);
        $reviewSvc = Mockery::mock(DocumentReviewService::class);
        $entityVersionRepo = Mockery::mock(EntityVersionRepositoryInterface::class);

        $entityVersionRepo->shouldReceive('findPublishedMetaByIdForVersionable')
            ->once()
            ->with('entity-anchor-uuid', Template::class, 'template-uuid')
            ->andReturn([
                'id' => 'entity-anchor-uuid',
                'version_number' => 1,
                'changelog' => 'desde entity',
            ]);

        // DMS-B07 (cluster B): la resolución de la versión publicada anclada vive ahora
        // en DocumentMigrationService; se prueba el método privado directamente sobre él.
        $migration = new DocumentMigrationService(
            $docRepo,
            $entityVersionRepo,
            Mockery::mock(DocumentBlockRepositoryInterface::class),
            $blockSvc,
            new DocumentMigrationPayloadResolver($docRepo, $entityVersionRepo, $blockSvc, new DocumentMigrationBlockDiffer),
        );

        $document = new Document;
        $document->forceFill([
            'template_version_id' => 'entity-anchor-uuid',
            'template_id' => 'template-uuid',
        ]);

        $method = new ReflectionMethod(DocumentMigrationService::class, 'resolveCurrentPublishedTemplateVersionMeta');
        $method->setAccessible(true);
        $meta = $method->invoke($migration, $document);

        $this->assertSame([
            'id' => 'entity-anchor-uuid',
            'version_number' => 1,
            'changelog' => 'desde entity',
        ], $meta);
    }

    public function test_resolve_review_candidates_uses_entity_snapshot_when_legacy_meta_missing(): void
    {
        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $tplRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $snap = Mockery::mock(SnapshotServiceInterface::class);
        $blockSvc = Mockery::mock(DocumentBlockService::class);
        $verSvc = Mockery::mock(DocumentVersionService::class);
        $shareSvc = Mockery::mock(DocumentShareService::class);
        $stateSvc = Mockery::mock(DocumentStateService::class);
        $reviewSvc = Mockery::mock(DocumentReviewService::class);
        $entityVersionRepo = Mockery::mock(EntityVersionRepositoryInterface::class);

        $entityVersion = new EntityVersion;
        $entityVersion->forceFill([
            'id' => 'entity-anchor-uuid',
            'snapshot_data' => [
                'reviewers' => [
                    'document_reviewers' => [
                        ['user_id' => 'reviewer-a'],
                    ],
                ],
            ],
        ]);

        $entityVersionRepo->shouldReceive('findPublishedByIdForVersionable')
            ->once()
            ->with('entity-anchor-uuid', Template::class, 'template-uuid')
            ->andReturn($entityVersion);

        $document = new Document;
        $document->forceFill([
            'template_version_id' => 'entity-anchor-uuid',
            'template_id' => 'template-uuid',
        ]);

        // DMS-B07: la resolución de candidatos vive ahora en DocumentReviewerResolutionService
        // (método público); se prueba directamente, sin reflexión sobre DocumentService.
        $reviewerResolver = new DocumentReviewerResolutionService(
            $entityVersionRepo,
            $tplRepo,
            new DocumentReviewModeResolver($entityVersionRepo),
            Mockery::mock(UserDirectoryRepositoryInterface::class),
        );
        $candidates = $reviewerResolver->resolveReviewCandidatesFromTemplateVersion($document);

        $this->assertSame([
            ['reviewer_id' => 'reviewer-a', 'stage' => 1],
        ], $candidates);
    }
}
