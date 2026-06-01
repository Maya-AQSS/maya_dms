<?php

namespace Tests\Unit\Support;

use App\Models\Document;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Support\DocumentReviewModeResolver;
use App\Support\TemplateHeadSnapshot;
use Mockery;
use Tests\TestCase;

final class DocumentReviewModeResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_uses_live_document_review_mode_independent_of_template_review_mode(): void
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
        $doc->forceFill(['template_id' => 'tpl-uuid']);
        $doc->setRelation('template', $template);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $evRepo->shouldNotReceive('findPublishedByIdForVersionable');

        $resolver = new DocumentReviewModeResolver($evRepo);

        $this->assertSame('sequential', $resolver->resolve($doc));
    }

    public function test_falls_back_to_review_mode_when_document_review_mode_missing(): void
    {
        $headEv = new EntityVersion;
        $headEv->forceFill([
            'snapshot_data' => [
                TemplateHeadSnapshot::JSON_TEMPLATE_KEY => [
                    'review_mode' => 'sequential',
                ],
            ],
        ]);

        $template = new Template;
        $template->setRelation('headVersion', $headEv);

        $doc = new Document;
        $doc->setRelation('template', $template);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);

        $resolver = new DocumentReviewModeResolver($evRepo);

        $this->assertSame('sequential', $resolver->resolve($doc));
    }
}
