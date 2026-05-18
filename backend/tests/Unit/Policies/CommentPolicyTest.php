<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\JwtUser;
use App\Models\Template;
use App\Models\TemplateReviewer;
use App\Policies\CommentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CommentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CommentPolicy;
    }

    // ─── Helper factories ─────────────────────────────────────────────────────

    private function makeJwtUser(string $id, array $permissions = []): JwtUser
    {
        return new JwtUser([
            'id'          => $id,
            'email'       => null,
            'name'        => null,
            'department'  => null,
            'permissions' => $permissions,
            'scope'       => '',
        ]);
    }

    private function makeTemplate(string $createdBy): Template
    {
        return Template::query()->forceCreate([
            'id'               => (string) Str::uuid(),
            'process_id'       => '00000000-0000-0000-0000-000000000001',
            'name'             => 'Test Template',
            'description'      => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by'       => $createdBy,
            'status'           => 'draft',
            'review_stages'    => 0,
            'review_mode'      => 'sequential',
        ]);
    }

    private function makeDocument(string $createdBy, string $ownerId): Document
    {
        $template = $this->makeTemplate($createdBy);

        return Document::query()->forceCreate([
            'id'          => (string) Str::uuid(),
            'process_id'  => '00000000-0000-0000-0000-000000000001',
            'template_id' => $template->id,
            'title'       => 'Test Document',
            'created_by'  => $createdBy,
            'owner_id'    => $ownerId,
            'status'      => 'draft',
        ]);
    }

    /**
     * Creates a Comment model without DB (commentable not loaded).
     */
    private function makeComment(string $authorId, ?object $commentable = null): Comment
    {
        $comment = new Comment;
        $comment->forceFill([
            'id'        => (string) Str::uuid(),
            'author_id' => $authorId,
        ]);

        if ($commentable !== null) {
            $comment->setRelation('commentable', $commentable);
        }

        return $comment;
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_author_can_delete_own_comment(): void
    {
        $authorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $user     = $this->makeJwtUser($authorId);
        $comment  = $this->makeComment($authorId);

        $this->assertTrue($this->policy->delete($user, $comment));
    }

    public function test_non_author_cannot_delete_when_no_commentable_loaded(): void
    {
        $user    = $this->makeJwtUser('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');
        $comment = $this->makeComment('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $this->assertFalse($this->policy->delete($user, $comment));
    }

    public function test_template_creator_can_delete_comment_on_own_template(): void
    {
        $creatorId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $authorId  = 'dddddddd-dddd-dddd-dddd-dddddddddddd';

        $user     = $this->makeJwtUser($creatorId);
        $template = $this->makeTemplate($creatorId);
        $comment  = $this->makeComment($authorId, $template);

        $this->assertTrue($this->policy->delete($user, $comment));
    }

    public function test_stranger_cannot_delete_comment_on_template_without_reviewer_role(): void
    {
        $creatorId  = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $strangerId = 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee';
        $authorId   = 'dddddddd-dddd-dddd-dddd-dddddddddddd';

        $user     = $this->makeJwtUser($strangerId);
        $template = $this->makeTemplate($creatorId);
        $comment  = $this->makeComment($authorId, $template);

        $this->assertFalse($this->policy->delete($user, $comment));
    }

    public function test_template_reviewer_can_delete_comment_on_reviewed_template(): void
    {
        $creatorId   = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $reviewerId  = 'ffffffff-ffff-ffff-ffff-ffffffffffff';
        $authorId    = 'dddddddd-dddd-dddd-dddd-dddddddddddd';

        $user     = $this->makeJwtUser($reviewerId);
        $template = $this->makeTemplate($creatorId);

        // Add reviewer relationship
        TemplateReviewer::query()->forceCreate([
            'template_id' => $template->id,
            'user_id'     => $reviewerId,
            'stage'       => 1,
        ]);

        // Comment on the template (load via DB so reviewers relation works)
        $templateFromDb = Template::withoutGlobalScopes()->findOrFail($template->id);
        $comment        = $this->makeComment($authorId, $templateFromDb);

        $this->assertTrue($this->policy->delete($user, $comment));
    }

    public function test_document_owner_can_delete_comment_on_own_document(): void
    {
        $ownerId  = '11111111-1111-1111-1111-111111111111';
        $authorId = '22222222-2222-2222-2222-222222222222';

        $user     = $this->makeJwtUser($ownerId);
        $document = $this->makeDocument($ownerId, $ownerId);
        $documentFromDb = Document::withoutGlobalScopes()->findOrFail($document->id);
        $comment  = $this->makeComment($authorId, $documentFromDb);

        $this->assertTrue($this->policy->delete($user, $comment));
    }

    public function test_stranger_cannot_delete_comment_on_document_without_reviewer(): void
    {
        $ownerId    = '11111111-1111-1111-1111-111111111111';
        $strangerId = '33333333-3333-3333-3333-333333333333';
        $authorId   = '22222222-2222-2222-2222-222222222222';

        $user     = $this->makeJwtUser($strangerId);
        $document = $this->makeDocument($ownerId, $ownerId);
        $documentFromDb = Document::withoutGlobalScopes()->findOrFail($document->id);
        $comment  = $this->makeComment($authorId, $documentFromDb);

        $this->assertFalse($this->policy->delete($user, $comment));
    }

    public function test_document_reviewer_can_delete_comment_on_reviewed_document(): void
    {
        $ownerId    = '11111111-1111-1111-1111-111111111111';
        $reviewerId = '44444444-4444-4444-4444-444444444444';
        $authorId   = '22222222-2222-2222-2222-222222222222';

        $user     = $this->makeJwtUser($reviewerId);
        $document = $this->makeDocument($ownerId, $ownerId);

        DocumentReview::query()->forceCreate([
            'id'          => (string) Str::uuid(),
            'document_id' => $document->id,
            'reviewer_id' => $reviewerId,
            'stage'       => 1,
        ]);

        $documentFromDb = Document::withoutGlobalScopes()->findOrFail($document->id);
        $comment        = $this->makeComment($authorId, $documentFromDb);

        $this->assertTrue($this->policy->delete($user, $comment));
    }
}
