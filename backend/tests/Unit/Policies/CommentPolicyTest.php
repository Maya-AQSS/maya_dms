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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function test_update_allowed_only_for_author(): void
    {
        $authorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $otherId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

        $comment = $this->makeComment($authorId);

        $this->assertTrue($this->policy->update($this->makeJwtUser($authorId), $comment));
        $this->assertFalse($this->policy->update($this->makeJwtUser($otherId), $comment));
    }

    public function test_author_can_delete_own_comment_without_delete_slug(): void
    {
        $authorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $template = $this->makeTemplate($authorId);
        $comment = $this->makeComment($authorId, $template);

        $this->assertTrue($this->policy->delete($this->makeJwtUser($authorId, ['comment-block.create']), $comment));
    }

    public function test_non_author_delete_denied_without_comment_block_delete_slug(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $otherId = 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee';
        $template = $this->makeTemplate($creatorId);
        $comment = $this->makeComment($otherId, $template);

        $this->assertFalse($this->policy->delete($this->makeJwtUser($creatorId, ['comment-block.create']), $comment));
    }

    public function test_author_can_delete_own_comment_when_creator_of_template(): void
    {
        $authorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $user = $this->makeJwtUser($authorId, ['comment-block.delete', 'comment-block.create']);
        $template = $this->makeTemplate($authorId);
        $comment = $this->makeComment($authorId, $template);

        $this->assertTrue($this->policy->delete($user, $comment));
    }

    public function test_template_creator_can_delete_others_comment(): void
    {
        $creatorId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $authorId = 'dddddddd-dddd-dddd-dddd-dddddddddddd';

        $user = $this->makeJwtUser($creatorId, ['comment-block.delete', 'comment-block.create']);
        $template = $this->makeTemplate($creatorId);
        $comment = $this->makeComment($authorId, $template);

        $this->assertTrue($this->policy->delete($user, $comment));
    }

    public function test_stranger_cannot_delete_comment_on_personal_template(): void
    {
        $creatorId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $strangerId = 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee';
        $authorId = 'dddddddd-dddd-dddd-dddd-dddddddddddd';

        $user = $this->makeJwtUser($strangerId, ['comment-block.delete', 'template.review']);
        $template = $this->makeTemplate($creatorId);
        $comment = $this->makeComment($authorId, $template);

        $this->assertFalse($this->policy->delete($user, $comment));
    }

    public function test_template_reviewer_can_delete_comment_in_review(): void
    {
        $creatorId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $reviewerId = 'ffffffff-ffff-ffff-ffff-ffffffffffff';
        $authorId = 'dddddddd-dddd-dddd-dddd-dddddddddddd';

        $user = $this->makeJwtUser($reviewerId, ['comment-block.delete', 'document.review', 'template.review']);
        $template = $this->makeTemplate($creatorId, 'in_review');

        TemplateReviewer::query()->forceCreate([
            'template_id' => $template->id,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $templateFromDb = Template::withoutGlobalScopes()->findOrFail($template->id);
        $comment = $this->makeComment($authorId, $templateFromDb);

        $this->assertTrue($this->policy->delete($user, $comment));
    }

    public function test_document_owner_can_delete_comment(): void
    {
        $ownerId = '11111111-1111-1111-1111-111111111111';
        $authorId = '22222222-2222-2222-2222-222222222222';

        $user = $this->makeJwtUser($ownerId, ['comment-block.delete', 'comment-block.create']);
        $document = $this->makeDocument($ownerId, $ownerId);
        $documentFromDb = Document::withoutGlobalScopes()->findOrFail($document->id);
        $comment = $this->makeComment($authorId, $documentFromDb);

        $this->assertTrue($this->policy->delete($user, $comment));
    }

    public function test_document_reviewer_can_delete_comment_in_review(): void
    {
        $ownerId = '11111111-1111-1111-1111-111111111111';
        $reviewerId = '44444444-4444-4444-4444-444444444444';
        $authorId = '22222222-2222-2222-2222-222222222222';

        $user = $this->makeJwtUser($reviewerId, ['comment-block.delete', 'document.review']);
        $document = $this->makeDocument($ownerId, $ownerId, 'in_review');

        DocumentReview::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $document->id,
            'reviewer_id' => $reviewerId,
            'stage' => 1,
        ]);

        $documentFromDb = Document::withoutGlobalScopes()->findOrFail($document->id);
        $comment = $this->makeComment($authorId, $documentFromDb);

        $this->assertTrue($this->policy->delete($user, $comment));
    }

    public function test_document_edit_share_collaborator_can_participate_in_draft(): void
    {
        $ownerId = '11111111-1111-1111-1111-111111111111';
        $collabId = '22222222-2222-2222-2222-222222222222';

        $document = $this->makeDocument($ownerId, $ownerId, 'draft');
        DB::table('document_shares')->insert([
            'id' => (string) Str::uuid(),
            'document_id' => $document->id,
            'user_id' => $collabId,
            'permission' => 'edit',
            'granted_by' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $documentFromDb = Document::withoutGlobalScopes()->findOrFail($document->id);
        $user = $this->makeJwtUser($collabId, ['comment-block.create']);

        $this->assertTrue($this->policy->mayParticipateOnDocument($user, $documentFromDb));
    }

    public function test_document_edit_share_collaborator_can_participate_when_rejected(): void
    {
        $ownerId = '11111111-1111-1111-1111-111111111111';
        $collabId = '22222222-2222-2222-2222-222222222222';

        $document = $this->makeDocument($ownerId, $ownerId, 'rejected');
        DB::table('document_shares')->insert([
            'id' => (string) Str::uuid(),
            'document_id' => $document->id,
            'user_id' => $collabId,
            'permission' => 'edit',
            'granted_by' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $documentFromDb = Document::withoutGlobalScopes()->findOrFail($document->id);
        $user = $this->makeJwtUser($collabId, ['comment-block.create']);

        $this->assertTrue($this->policy->mayParticipateOnDocument($user, $documentFromDb));
    }

    public function test_document_edit_share_read_only_cannot_participate(): void
    {
        $ownerId = '11111111-1111-1111-1111-111111111111';
        $collabId = '22222222-2222-2222-2222-222222222222';

        $document = $this->makeDocument($ownerId, $ownerId, 'draft');
        DB::table('document_shares')->insert([
            'id' => (string) Str::uuid(),
            'document_id' => $document->id,
            'user_id' => $collabId,
            'permission' => 'read',
            'granted_by' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $documentFromDb = Document::withoutGlobalScopes()->findOrFail($document->id);
        $user = $this->makeJwtUser($collabId, ['comment-block.create']);

        $this->assertFalse($this->policy->mayParticipateOnDocument($user, $documentFromDb));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeJwtUser(string $id, array $permissions = []): JwtUser
    {
        return new JwtUser([
            'id' => $id,
            'email' => null,
            'name' => null,
            'department' => null,
            'permissions' => $permissions,
            'scope' => '',
        ]);
    }

    private function makeTemplate(string $createdBy, string $status = 'draft'): Template
    {
        return Template::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Test Template',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by' => $createdBy,
            'status' => $status,
            'review_stages' => 0,
            'review_mode' => 'parallel',
        ]);
    }

    private function makeDocument(string $createdBy, string $ownerId, string $status = 'draft'): Document
    {
        $templateId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Tpl',
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by' => $createdBy,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'parallel',
        ]);

        return Document::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'template_id' => $templateId,
            'title' => 'Test Document',
            'created_by' => $createdBy,
            'owner_id' => $ownerId,
            'status' => $status,
        ]);
    }

    private function makeComment(string $authorId, ?object $commentable = null): Comment
    {
        $comment = new Comment;
        $comment->forceFill([
            'id' => (string) Str::uuid(),
            'author_id' => $authorId,
        ]);

        if ($commentable !== null) {
            $comment->setRelation('commentable', $commentable);
        }

        return $comment;
    }
}
