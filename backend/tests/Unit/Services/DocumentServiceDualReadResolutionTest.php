<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Repositories\Contracts\DocumentBlockRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TeamReadRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Services\DocumentBlockService;
use App\Services\DocumentMigrationBlockDiffer;
use App\Services\DocumentMigrationPayloadResolver;
use App\Services\DocumentReviewService;
use App\Services\DocumentService;
use App\Services\DocumentShareService;
use App\Services\DocumentStateService;
use App\Services\DocumentVersionService;
use App\Services\TemplateContextResolver;
use App\Support\DocumentReviewModeResolver;
use Maya\Messaging\Publishers\NotificationPublisher;
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

        $service = new DocumentService(
            $docRepo,
            $tplRepo,
            $snap,
            $blockSvc,
            $verSvc,
            $shareSvc,
            $stateSvc,
            $reviewSvc,
            $entityVersionRepo,
            Mockery::mock(DocumentBlockRepositoryInterface::class),
            Mockery::mock(TemplateContextResolver::class),
            Mockery::mock(AcademicHierarchyRepositoryInterface::class),
            Mockery::mock(TeamReadRepositoryInterface::class),
            Mockery::mock(NotificationPublisher::class),
            new DocumentReviewModeResolver($entityVersionRepo),
            new DocumentMigrationPayloadResolver($docRepo, $entityVersionRepo, $blockSvc, new DocumentMigrationBlockDiffer),
            Mockery::mock(UserDirectoryRepositoryInterface::class),
            Mockery::mock(CommentRepositoryInterface::class),
        );

        $document = new Document;
        $document->forceFill([
            'template_version_id' => 'entity-anchor-uuid',
            'template_id' => 'template-uuid',
        ]);

        $method = new ReflectionMethod(DocumentService::class, 'resolveCurrentPublishedTemplateVersionMeta');
        $method->setAccessible(true);
        $meta = $method->invoke($service, $document);

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

        $service = new DocumentService(
            $docRepo,
            $tplRepo,
            $snap,
            $blockSvc,
            $verSvc,
            $shareSvc,
            $stateSvc,
            $reviewSvc,
            $entityVersionRepo,
            Mockery::mock(DocumentBlockRepositoryInterface::class),
            Mockery::mock(TemplateContextResolver::class),
            Mockery::mock(AcademicHierarchyRepositoryInterface::class),
            Mockery::mock(TeamReadRepositoryInterface::class),
            Mockery::mock(NotificationPublisher::class),
            new DocumentReviewModeResolver($entityVersionRepo),
            new DocumentMigrationPayloadResolver($docRepo, $entityVersionRepo, $blockSvc, new DocumentMigrationBlockDiffer),
            Mockery::mock(UserDirectoryRepositoryInterface::class),
            Mockery::mock(CommentRepositoryInterface::class),
        );

        $document = new Document;
        $document->forceFill([
            'template_version_id' => 'entity-anchor-uuid',
            'template_id' => 'template-uuid',
        ]);

        $method = new ReflectionMethod(DocumentService::class, 'resolveReviewCandidatesFromTemplateVersion');
        $method->setAccessible(true);
        $candidates = $method->invoke($service, $document);

        $this->assertSame([
            ['reviewer_id' => 'reviewer-a', 'stage' => 1],
        ], $candidates);
    }
}
