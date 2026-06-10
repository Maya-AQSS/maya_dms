<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Services\DocumentReviewService;
use App\Services\DocumentStateService;
use App\Support\DocumentReviewModeResolver;
use App\Support\TemplateHeadSnapshot;
use Maya\Messaging\Publishers\NotificationPublisher;
use Mockery;
use Tests\TestCase;

class DocumentReviewServiceReviewModeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(
        DocumentRepositoryInterface $docRepo,
        EntityVersionRepositoryInterface $evRepo,
    ): DocumentReviewService {
        return new DocumentReviewService(
            $docRepo,
            $evRepo,
            Mockery::mock(SnapshotServiceInterface::class),
            Mockery::mock(DocumentStateService::class),
            Mockery::mock(NotificationPublisher::class),
            new DocumentReviewModeResolver($evRepo),
            Mockery::mock(UserDirectoryRepositoryInterface::class),
        );
    }

    public function test_prefers_live_document_review_mode_over_anchored_sequential(): void
    {
        $headEv = new EntityVersion;
        $headEv->forceFill([
            'snapshot_data' => [
                TemplateHeadSnapshot::JSON_TEMPLATE_KEY => [
                    'review_mode' => 'sequential',
                    'document_review_mode' => 'parallel',
                ],
            ],
        ]);

        $template = new Template;
        $template->setRelation('headVersion', $headEv);

        $doc = new Document;
        $doc->forceFill([
            'template_version_id' => 'ev-uuid',
            'template_id' => 'tpl-uuid',
        ]);
        $doc->setRelation('template', $template);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $evRepo->shouldReceive('findPublishedByIdForVersionable')
            ->never();

        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);

        $service = $this->makeService($docRepo, $evRepo);

        $this->assertSame('parallel', $service->resolveReviewMode($doc));
    }

    public function test_resolves_sequential_mode_from_anchored_entity_version_when_live_unset(): void
    {
        $doc = new Document;
        $doc->forceFill([
            'template_version_id' => 'ev-uuid',
            'template_id' => 'tpl-uuid',
        ]);
        // Sin modo "live": relaciones cargadas explícitamente a null para que
        // loadMissing no intente resolverlas contra la BD (ids no-UUID de prueba).
        $doc->setRelation('headVersion', null);
        $doc->setRelation('template', null);

        $entityVersion = new EntityVersion;
        $entityVersion->forceFill([
            'id' => 'ev-uuid',
            'snapshot_data' => [
                'template' => [
                    'review_mode' => 'parallel',
                    'document_review_mode' => 'sequential',
                ],
            ],
        ]);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $evRepo->shouldReceive('findPublishedByIdForVersionable')
            ->once()
            ->with('ev-uuid', Template::class, 'tpl-uuid')
            ->andReturn($entityVersion);

        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);

        $service = $this->makeService($docRepo, $evRepo);

        $this->assertSame('sequential', $service->resolveReviewMode($doc));
    }

    public function test_resolves_parallel_mode_from_anchored_entity_version_when_live_unset(): void
    {
        $doc = new Document;
        $doc->forceFill([
            'template_version_id' => 'ev-uuid',
            'template_id' => 'tpl-uuid',
        ]);
        // Sin modo "live": relaciones cargadas explícitamente a null para que
        // loadMissing no intente resolverlas contra la BD (ids no-UUID de prueba).
        $doc->setRelation('headVersion', null);
        $doc->setRelation('template', null);

        $entityVersion = new EntityVersion;
        $entityVersion->forceFill([
            'id' => 'ev-uuid',
            'snapshot_data' => ['template' => ['review_mode' => 'parallel']],
        ]);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $evRepo->shouldReceive('findPublishedByIdForVersionable')
            ->once()
            ->with('ev-uuid', Template::class, 'tpl-uuid')
            ->andReturn($entityVersion);

        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);

        $service = $this->makeService($docRepo, $evRepo);

        $this->assertSame('parallel', $service->resolveReviewMode($doc));
    }

    public function test_falls_back_to_live_template_review_mode_when_document_mode_unset(): void
    {
        $headEv = new EntityVersion;
        $headEv->forceFill([
            'snapshot_data' => [
                TemplateHeadSnapshot::JSON_TEMPLATE_KEY => ['review_mode' => 'sequential'],
            ],
        ]);

        $template = new Template;
        $template->setRelation('headVersion', $headEv);

        $doc = new Document;
        $doc->forceFill([
            'template_version_id' => null,
            'template_id' => null,
        ]);
        $doc->setRelation('template', $template);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $evRepo->shouldNotReceive('findPublishedByIdForVersionable');

        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);

        $service = $this->makeService($docRepo, $evRepo);

        $this->assertSame('sequential', $service->resolveReviewMode($doc));
    }

    public function test_falls_back_to_live_template_when_entity_version_not_found(): void
    {
        $headEv = new EntityVersion;
        $headEv->forceFill([
            'snapshot_data' => [
                TemplateHeadSnapshot::JSON_TEMPLATE_KEY => [
                    'review_mode' => 'parallel',
                    'document_review_mode' => 'sequential',
                ],
            ],
        ]);

        $template = new Template;
        $template->setRelation('headVersion', $headEv);

        $doc = new Document;
        $doc->forceFill([
            'template_version_id' => 'ev-uuid',
            'template_id' => 'tpl-uuid',
        ]);
        $doc->setRelation('template', $template);

        // El modo "live" del head de plantilla tiene prioridad sobre el ancla,
        // por lo que el repositorio de entity versions no se consulta.
        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $evRepo->shouldNotReceive('findPublishedByIdForVersionable');

        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);

        $service = $this->makeService($docRepo, $evRepo);

        $this->assertSame('sequential', $service->resolveReviewMode($doc));
    }
}
