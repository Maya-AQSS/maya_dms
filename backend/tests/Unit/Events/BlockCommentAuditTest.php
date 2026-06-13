<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\BlockCommentCreated;
use App\Events\BlockCommentDeleted;
use App\Events\BlockCommentMarkedRead;
use App\Events\BlockCommentsMarkedRead;
use App\Events\BlockCommentUpdated;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\Template;
use App\Models\TemplateBlock;
use Tests\TestCase;

// Antes extendía PHPUnit\Framework\TestCase (test puro), pero `new Comment`
// dispara `Comment::booted()` → `Schema::hasTable('template_shares')`, que
// requiere el contenedor de Laravel ("A facade root has not been set." si no).
// Tests\TestCase arranca la app (sin RefreshDatabase) y resuelve el facade.
final class BlockCommentAuditTest extends TestCase
{
    public function test_created_payload_is_anchored_to_template_and_block(): void
    {
        $comment = $this->makeComment(Template::class, 'tpl-1', TemplateBlock::class, 'blk-1');

        $payload = (new BlockCommentCreated($comment, 'actor-1'))->toAuditPayload();

        $this->assertSame('comment_created', $payload['action']);
        $this->assertSame('template', $payload['entityType']);
        $this->assertSame('tpl-1', $payload['entityId']);
        $this->assertSame('blk-1', $payload['blockId']);
        $this->assertSame('actor-1', $payload['userId']);
        $this->assertSame('cmt-1', $payload['newValue']['comment_id']);
    }

    public function test_updated_payload_includes_previous_and_new_body_excerpt(): void
    {
        $comment = $this->makeComment(Document::class, 'doc-1', DocumentBlock::class, 'blk-2');
        $comment->body = 'Texto nuevo';

        $payload = (new BlockCommentUpdated($comment, 'actor-2', 'Texto anterior'))->toAuditPayload();

        $this->assertSame('comment_updated', $payload['action']);
        $this->assertSame('document', $payload['entityType']);
        $this->assertSame('Texto anterior', $payload['previousValue']['body_excerpt']);
        $this->assertSame('Texto nuevo', $payload['newValue']['body_excerpt']);
    }

    public function test_marked_read_payload_anchors_to_parent_and_block(): void
    {
        $comment = $this->makeComment(Document::class, 'doc-9', DocumentBlock::class, 'blk-9');

        $payload = (new BlockCommentMarkedRead($comment, 'reader-1'))->toAuditPayload();

        $this->assertSame('comment_marked_read', $payload['action']);
        $this->assertSame('reader-1', $payload['userId']);
        $this->assertSame('blk-9', $payload['blockId']);
    }

    public function test_bulk_marked_read_payload_includes_marked_count(): void
    {
        $payload = (new BlockCommentsMarkedRead(
            commentableType: Template::class,
            commentableId: 'tpl-1',
            commentableVersion: 2,
            blockableType: TemplateBlock::class,
            blockableId: 'blk-1',
            readerUserId: 'reader-2',
            markedCount: 3,
        ))->toAuditPayload();

        $this->assertSame('comments_marked_read', $payload['action']);
        $this->assertSame(3, $payload['newValue']['marked_count']);
        $this->assertSame('template', $payload['entityType']);
    }

    public function test_deleted_payload_anchors_to_parent_document(): void
    {
        $comment = $this->makeComment(Document::class, 'doc-9', DocumentBlock::class, 'blk-9');

        $payload = (new BlockCommentDeleted($comment, 'actor-3'))->toAuditPayload();

        $this->assertSame('comment_deleted', $payload['action']);
        $this->assertSame('doc-9', $payload['entityId']);
        $this->assertNull($payload['newValue'] ?? null);
        $this->assertSame('cmt-1', $payload['previousValue']['comment_id']);
    }

    private function makeComment(
        string $commentableType,
        string $commentableId,
        string $blockableType,
        string $blockableId,
    ): Comment {
        $comment = new Comment;
        $comment->setAttribute('id', 'cmt-1');
        $comment->commentable_type = $commentableType;
        $comment->commentable_id = $commentableId;
        $comment->commentable_version = 1;
        $comment->blockable_type = $blockableType;
        $comment->blockable_id = $blockableId;
        $comment->author_id = 'author-1';
        $comment->body = 'Comentario de prueba';

        return $comment;
    }
}
