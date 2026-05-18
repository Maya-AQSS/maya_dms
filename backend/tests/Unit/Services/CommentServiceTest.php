<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\Comments\CommentDto;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Services\CommentService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for CommentService uncovered branches (78.4% → ≥80%).
 *
 * Uncovered lines:
 *   26     - findOrFail / findModelOrFail
 *   67-68  - partial blockable pair (type XOR id) → ValidationException
 *   73-74  - assertParentBelongsToResource call (covered via createForResource)
 *   122-123- Template blockable with wrong blockable_type → ValidationException
 *   140-141- Document blockable with wrong blockable_type → ValidationException
 *   169-170- parent deleted_at !== null → ValidationException
 *   175-176- parent commentable_type/id/version mismatch → ValidationException
 */
final class CommentServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeComment(array $attributes = []): Comment
    {
        $c = new Comment;
        $c->forceFill(array_merge([
            'id'                 => 'comment-uuid',
            'commentable_type'   => Document::class,
            'commentable_id'     => 'doc-uuid',
            'commentable_version'=> 1,
            'blockable_type'     => null,
            'blockable_id'       => null,
            'parent_id'          => null,
            'author_id'          => 'author-uuid',
            'body'               => 'Test comment body',
            'resolved'           => false,
            'resolved_by'        => null,
            'resolved_at'        => null,
            'deleted_at'         => null,
        ], $attributes));

        return $c;
    }

    private function makeService(CommentRepositoryInterface $repo): CommentService
    {
        return new CommentService($repo);
    }

    // ─── findOrFail / findModelOrFail ────────────────────────────────────────

    public function test_find_or_fail_returns_dto(): void
    {
        $comment = $this->makeComment();
        $repo    = Mockery::mock(CommentRepositoryInterface::class);
        $repo->shouldReceive('findOrFail')->once()->with('comment-uuid')->andReturn($comment);

        $service = $this->makeService($repo);
        $result  = $service->findOrFail('comment-uuid');

        $this->assertInstanceOf(CommentDto::class, $result);
        $this->assertSame('comment-uuid', $result->id);
    }

    public function test_find_model_or_fail_returns_model(): void
    {
        $comment = $this->makeComment();
        $repo    = Mockery::mock(CommentRepositoryInterface::class);
        $repo->shouldReceive('findOrFail')->once()->with('comment-uuid')->andReturn($comment);

        $service = $this->makeService($repo);
        $result  = $service->findModelOrFail('comment-uuid');

        $this->assertInstanceOf(Comment::class, $result);
    }

    // ─── createForResource: partial blockable pair ───────────────────────────

    public function test_create_throws_when_blockable_type_set_but_id_null(): void
    {
        $repo    = Mockery::mock(CommentRepositoryInterface::class);
        $service = $this->makeService($repo);

        $this->expectException(ValidationException::class);

        $service->createForResource(
            commentableType: Document::class,
            commentableId: 'doc-uuid',
            commentableVersion: 1,
            blockableType: DocumentBlock::class,
            blockableId: null,          // mismatch: type set, id null
            parentId: null,
            authorId: 'author-uuid',
            body: 'Body',
        );
    }

    public function test_create_throws_when_blockable_id_set_but_type_null(): void
    {
        $repo    = Mockery::mock(CommentRepositoryInterface::class);
        $service = $this->makeService($repo);

        $this->expectException(ValidationException::class);

        $service->createForResource(
            commentableType: Document::class,
            commentableId: 'doc-uuid',
            commentableVersion: 1,
            blockableType: null,        // mismatch: type null, id set
            blockableId: 'block-uuid',
            parentId: null,
            authorId: 'author-uuid',
            body: 'Body',
        );
    }

    // ─── createForResource: invalid commentable_type ──────────────────────────

    public function test_create_throws_for_invalid_commentable_type(): void
    {
        $repo    = Mockery::mock(CommentRepositoryInterface::class);
        $service = $this->makeService($repo);

        $this->expectException(ValidationException::class);

        $service->createForResource(
            commentableType: 'App\\Models\\SomethingElse', // not in ALLOWED list
            commentableId: 'some-uuid',
            commentableVersion: 1,
            blockableType: null,
            blockableId: null,
            parentId: null,
            authorId: 'author-uuid',
            body: 'Body',
        );
    }

    // ─── assertBlockBelongsToResource: Template with wrong blockable_type ─────

    public function test_create_throws_when_template_blockable_type_is_not_template_block(): void
    {
        $repo = Mockery::mock(CommentRepositoryInterface::class);
        $repo->shouldNotReceive('findWithoutScopesById');
        $service = $this->makeService($repo);

        $this->expectException(ValidationException::class);

        $service->createForResource(
            commentableType: Template::class,
            commentableId: 'tmpl-uuid',
            commentableVersion: 1,
            blockableType: DocumentBlock::class,  // wrong: should be TemplateBlock
            blockableId: 'block-uuid',
            parentId: null,
            authorId: 'author-uuid',
            body: 'Body',
        );
    }

    // ─── assertBlockBelongsToResource: Template block does not belong ─────────

    public function test_create_throws_when_template_block_does_not_belong_to_template(): void
    {
        $repo = Mockery::mock(CommentRepositoryInterface::class);
        $repo->shouldReceive('findWithoutScopesById')->andReturn(null);
        $repo->shouldReceive('existsTemplateBlockForTemplate')
            ->once()
            ->with('block-uuid', 'tmpl-uuid')
            ->andReturn(false);

        $service = $this->makeService($repo);

        $this->expectException(ValidationException::class);

        $service->createForResource(
            commentableType: Template::class,
            commentableId: 'tmpl-uuid',
            commentableVersion: 1,
            blockableType: TemplateBlock::class,
            blockableId: 'block-uuid',
            parentId: null,
            authorId: 'author-uuid',
            body: 'Body',
        );
    }

    // ─── assertBlockBelongsToResource: Document with wrong blockable_type ─────

    public function test_create_throws_when_document_blockable_type_is_not_document_block(): void
    {
        $repo = Mockery::mock(CommentRepositoryInterface::class);
        $repo->shouldNotReceive('existsDocumentBlockForDocument');
        $service = $this->makeService($repo);

        $this->expectException(ValidationException::class);

        $service->createForResource(
            commentableType: Document::class,
            commentableId: 'doc-uuid',
            commentableVersion: 1,
            blockableType: TemplateBlock::class,  // wrong: should be DocumentBlock
            blockableId: 'block-uuid',
            parentId: null,
            authorId: 'author-uuid',
            body: 'Body',
        );
    }

    // ─── assertBlockBelongsToResource: Document block does not belong ─────────

    public function test_create_throws_when_document_block_does_not_belong_to_document(): void
    {
        $repo = Mockery::mock(CommentRepositoryInterface::class);
        $repo->shouldReceive('findWithoutScopesById')->andReturn(null);
        $repo->shouldReceive('existsDocumentBlockForDocument')
            ->once()
            ->with('block-uuid', 'doc-uuid')
            ->andReturn(false);

        $service = $this->makeService($repo);

        $this->expectException(ValidationException::class);

        $service->createForResource(
            commentableType: Document::class,
            commentableId: 'doc-uuid',
            commentableVersion: 1,
            blockableType: DocumentBlock::class,
            blockableId: 'block-uuid',
            parentId: null,
            authorId: 'author-uuid',
            body: 'Body',
        );
    }

    // ─── assertParentBelongsToResource: parent not found ─────────────────────

    public function test_create_throws_when_parent_not_found(): void
    {
        $repo = Mockery::mock(CommentRepositoryInterface::class);
        $repo->shouldReceive('findWithoutScopesById')
            ->once()
            ->with('parent-uuid')
            ->andReturn(null);

        $service = $this->makeService($repo);

        $this->expectException(ValidationException::class);

        $service->createForResource(
            commentableType: Document::class,
            commentableId: 'doc-uuid',
            commentableVersion: 1,
            blockableType: null,
            blockableId: null,
            parentId: 'parent-uuid',
            authorId: 'author-uuid',
            body: 'Body',
        );
    }

    // ─── assertParentBelongsToResource: parent is soft-deleted ───────────────

    public function test_create_throws_when_parent_is_soft_deleted(): void
    {
        $parent = $this->makeComment(['id' => 'parent-uuid', 'deleted_at' => now()]);

        $repo = Mockery::mock(CommentRepositoryInterface::class);
        $repo->shouldReceive('findWithoutScopesById')
            ->once()
            ->with('parent-uuid')
            ->andReturn($parent);

        $service = $this->makeService($repo);

        $this->expectException(ValidationException::class);

        $service->createForResource(
            commentableType: Document::class,
            commentableId: 'doc-uuid',
            commentableVersion: 1,
            blockableType: null,
            blockableId: null,
            parentId: 'parent-uuid',
            authorId: 'author-uuid',
            body: 'Body',
        );
    }

    // ─── assertParentBelongsToResource: parent belongs to different resource ──

    public function test_create_throws_when_parent_belongs_to_different_resource(): void
    {
        // Parent belongs to a different document
        $parent = $this->makeComment([
            'id'                  => 'parent-uuid',
            'commentable_type'    => Document::class,
            'commentable_id'      => 'other-doc-uuid',  // different resource
            'commentable_version' => 1,
            'blockable_type'      => null,
            'blockable_id'        => null,
        ]);

        $repo = Mockery::mock(CommentRepositoryInterface::class);
        $repo->shouldReceive('findWithoutScopesById')
            ->once()
            ->with('parent-uuid')
            ->andReturn($parent);

        $service = $this->makeService($repo);

        $this->expectException(ValidationException::class);

        $service->createForResource(
            commentableType: Document::class,
            commentableId: 'doc-uuid',          // different from parent's doc
            commentableVersion: 1,
            blockableType: null,
            blockableId: null,
            parentId: 'parent-uuid',
            authorId: 'author-uuid',
            body: 'Body',
        );
    }

    // ─── assertParentBelongsToResource: parent belongs to different block ─────

    public function test_create_throws_when_parent_belongs_to_different_block(): void
    {
        // Parent is attached to a different block
        $parent = $this->makeComment([
            'id'                  => 'parent-uuid',
            'commentable_type'    => Document::class,
            'commentable_id'      => 'doc-uuid',
            'commentable_version' => 1,
            'blockable_type'      => DocumentBlock::class,
            'blockable_id'        => 'block-other',  // different block
        ]);

        $repo = Mockery::mock(CommentRepositoryInterface::class);
        $repo->shouldReceive('findWithoutScopesById')
            ->once()
            ->with('parent-uuid')
            ->andReturn($parent);

        $service = $this->makeService($repo);

        $this->expectException(ValidationException::class);

        $service->createForResource(
            commentableType: Document::class,
            commentableId: 'doc-uuid',
            commentableVersion: 1,
            blockableType: DocumentBlock::class,
            blockableId: 'block-mine',         // different from parent's block
            parentId: 'parent-uuid',
            authorId: 'author-uuid',
            body: 'Body',
        );
    }

    // ─── delete ──────────────────────────────────────────────────────────────

    public function test_delete_calls_delete_on_model(): void
    {
        $repo    = Mockery::mock(CommentRepositoryInterface::class);
        $service = $this->makeService($repo);

        $deleted = false;
        $comment = Mockery::mock(Comment::class);
        $comment->shouldReceive('delete')->once()->andReturnUsing(function () use (&$deleted) {
            $deleted = true;
        });

        $service->delete($comment);

        $this->assertTrue($deleted, 'Comment::delete() was called');
    }
}
