<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\AnchoredComment\AnchoredCommentDto;
use App\Models\AnchoredComment;
use App\Models\Document;
use App\Repositories\Contracts\AnchoredCommentRepositoryInterface;
use App\Services\AnchoredCommentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Mockery;
use Tests\TestCase;

final class AnchoredCommentServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeAnchor(array $attributes = []): AnchoredComment
    {
        $anchor = new AnchoredComment;
        $anchor->forceFill(array_merge([
            'id' => 'anchor-uuid',
            'comment_id' => 'comment-uuid',
            'resource_type' => Document::class,
            'resource_id' => 'doc-uuid',
            'anchor_from' => 10,
            'anchor_to' => 20,
            'anchor_text_snapshot' => 'text',
            'anchor_is_valid' => true,
            'anchor_last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return $anchor;
    }

    private function makeService(AnchoredCommentRepositoryInterface $repo): AnchoredCommentService
    {
        return new AnchoredCommentService($repo);
    }

    public function test_list_for_resource_returns_dtos(): void
    {
        $anchor = $this->makeAnchor();
        $repo = Mockery::mock(AnchoredCommentRepositoryInterface::class);
        $repo->shouldReceive('findByResource')
            ->once()
            ->with(Document::class, 'doc-uuid')
            ->andReturn(new Collection([$anchor]));

        $service = $this->makeService($repo);
        $result = $service->listForResource(Document::class, 'doc-uuid');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(AnchoredCommentDto::class, $result[0]);
    }

    public function test_create_for_resource_creates_anchor(): void
    {
        $anchor = $this->makeAnchor();
        $repo = Mockery::mock(AnchoredCommentRepositoryInterface::class);
        $repo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($attrs) {
                return $attrs['comment_id'] === 'comment-uuid'
                    && $attrs['resource_type'] === Document::class
                    && $attrs['resource_id'] === 'doc-uuid'
                    && $attrs['anchor_from'] === 10
                    && $attrs['anchor_to'] === 20;
            }))
            ->andReturn($anchor);

        $service = $this->makeService($repo);
        $result = $service->createForResource(
            Document::class,
            'doc-uuid',
            'comment-uuid',
            10,
            20,
            'text'
        );

        $this->assertInstanceOf(AnchoredCommentDto::class, $result);
        $this->assertSame('comment-uuid', $result->commentId);
    }

    public function test_get_for_resource_returns_null_if_anchor_not_found(): void
    {
        $repo = Mockery::mock(AnchoredCommentRepositoryInterface::class);
        $repo->shouldReceive('findByIdOrFail')
            ->once()
            ->andThrow(new ModelNotFoundException);

        $service = $this->makeService($repo);
        $result = $service->getForResource(Document::class, 'doc-uuid', 'anchor-uuid');

        $this->assertNull($result);
    }

    public function test_get_for_resource_returns_null_if_anchor_belongs_to_different_resource(): void
    {
        $anchor = $this->makeAnchor(['resource_id' => 'other-uuid']);
        $repo = Mockery::mock(AnchoredCommentRepositoryInterface::class);
        $repo->shouldReceive('findByIdOrFail')
            ->once()
            ->with('anchor-uuid')
            ->andReturn($anchor);

        $service = $this->makeService($repo);
        $result = $service->getForResource(Document::class, 'doc-uuid', 'anchor-uuid');

        $this->assertNull($result);
    }

    public function test_delete_anchor_deletes_via_repository(): void
    {
        $anchor = $this->makeAnchor();
        $repo = Mockery::mock(AnchoredCommentRepositoryInterface::class);
        $repo->shouldReceive('findByIdOrFail')
            ->once()
            ->with('anchor-uuid')
            ->andReturn($anchor);
        $repo->shouldReceive('delete')
            ->once()
            ->with($anchor);

        $service = $this->makeService($repo);
        $service->deleteAnchor('anchor-uuid');

        $this->assertTrue(true); // Verify no exception thrown
    }
}
