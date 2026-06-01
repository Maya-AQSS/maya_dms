<?php

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Support\TemplateHeadSnapshot;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Services\DocumentReviewService;
use App\Services\DocumentStateService;
use App\Support\DocumentReviewModeResolver;
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
        );
    }

    public function test_prefers_live_template_parallel_over_anchored_sequential(): void
    {
        $headEv = new EntityVersion;
        $headEv->forceFill(['snapshot_data' => [TemplateHeadSnapshot::JSON_TEMPLATE_KEY => ['review_mode' => 'parallel']]]);

        $template = new Template;
        $template->setRelation('headVersion', $headEv);

        $doc = new Document;
        $doc->forceFill([
            'template_version_id' => 'ev-uuid',
            'template_id'         => 'tpl-uuid',
        ]);
        $doc->setRelation('template', $template);

        $entityVersion = new EntityVersion;
        $entityVersion->forceFill([
            'id'            => 'ev-uuid',
            'snapshot_data' => ['template' => ['review_mode' => 'sequential']],
        ]);

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
            'template_id'         => 'tpl-uuid',
        ]);

        $entityVersion = new EntityVersion;
        $entityVersion->forceFill([
            'id'            => 'ev-uuid',
            'snapshot_data' => ['template' => ['review_mode' => 'sequential']],
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
            'template_id'         => 'tpl-uuid',
        ]);

        $entityVersion = new EntityVersion;
        $entityVersion->forceFill([
            'id'            => 'ev-uuid',
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

    public function test_falls_back_to_live_template_when_no_version_anchor(): void
    {
        $headEv = new EntityVersion;
        $headEv->forceFill(['snapshot_data' => [TemplateHeadSnapshot::JSON_TEMPLATE_KEY => ['review_mode' => 'sequential']]]);

        $template = new Template;
        $template->setRelation('headVersion', $headEv);

        $doc = new Document;
        $doc->forceFill([
            'template_version_id' => null,
            'template_id'         => null,
        ]);
        $doc->setRelation('template', $template);

        $evRepo  = Mockery::mock(EntityVersionRepositoryInterface::class);
        $evRepo->shouldNotReceive('findPublishedByIdForVersionable');

        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);

        $service = $this->makeService($docRepo, $evRepo);

        $this->assertSame('sequential', $service->resolveReviewMode($doc));
    }

    public function test_falls_back_to_live_template_when_entity_version_not_found(): void
    {
        $headEv = new EntityVersion;
        $headEv->forceFill(['snapshot_data' => [TemplateHeadSnapshot::JSON_TEMPLATE_KEY => ['review_mode' => 'sequential']]]);

        $template = new Template;
        $template->setRelation('headVersion', $headEv);

        $doc = new Document;
        $doc->forceFill([
            'template_version_id' => 'ev-uuid',
            'template_id'         => 'tpl-uuid',
        ]);
        $doc->setRelation('template', $template);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $evRepo->shouldReceive('findPublishedByIdForVersionable')
            ->once()
            ->with('ev-uuid', Template::class, 'tpl-uuid')
            ->andReturn(null);

        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);

        $service = $this->makeService($docRepo, $evRepo);

        $this->assertSame('sequential', $service->resolveReviewMode($doc));
    }
}
