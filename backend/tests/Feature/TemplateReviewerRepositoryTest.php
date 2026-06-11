<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateDocumentReviewer;
use App\Models\TemplateReviewer;
use App\Repositories\Eloquent\TemplateReviewerRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cobertura de integración para TemplateReviewerRepository.
 * Usa RefreshDatabase contra maya_dms_test (PostgreSQL real).
 */
class TemplateReviewerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TemplateReviewerRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TemplateReviewerRepository;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeTemplate(string $status = 'draft', string $visibility = 'personal'): Template
    {
        $templateId = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla test repo',
            'description' => null,
            'visibility_level' => $visibility,
            'study_id' => null,
            'created_by' => (string) Str::uuid(),
            'status' => $status,
            'review_stages' => 0,
            'review_mode' => 'parallel',
        ]);

        // Use withoutGlobalScopes to bypass user_access and join_head_entity_version scopes
        // that require an authenticated user and entity_versions join.
        return Template::withoutGlobalScopes()->findOrFail($templateId);
    }

    private function addBlock(Template $template, int $sortOrder = 0): TemplateBlock
    {
        $block = TemplateBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => (string) $template->getKey(),
            'title' => 'Bloque test',
            'default_content' => ['type' => 'doc', 'content' => []],
            'block_state' => 'editable',
            'sort_order' => $sortOrder,
        ]);

        return $block;
    }

    private function addReviewer(Template $template, string $userId, int $stage = 1, string $status = 'pending'): TemplateReviewer
    {
        return TemplateReviewer::query()->forceCreate([
            'template_id' => (string) $template->getKey(),
            'user_id' => $userId,
            'stage' => $stage,
            'status' => $status,
        ]);
    }

    private function addDocumentReviewer(Template $template, string $userId, int $stage = 1): TemplateDocumentReviewer
    {
        return TemplateDocumentReviewer::query()->forceCreate([
            'template_id' => (string) $template->getKey(),
            'user_id' => $userId,
            'stage' => $stage,
        ]);
    }

    // -----------------------------------------------------------------------
    // findTemplateIdsWithReviewer
    // -----------------------------------------------------------------------

    public function test_find_template_ids_with_reviewer_returns_only_matching_templates(): void
    {
        $userId = 'user-repo-01';
        $t1 = $this->makeTemplate();
        $t2 = $this->makeTemplate();
        $t3 = $this->makeTemplate();

        $this->addReviewer($t1, $userId);
        $this->addReviewer($t3, $userId);
        // t2 has no reviewer for $userId

        $result = $this->repo->findTemplateIdsWithReviewer(
            [(string) $t1->getKey(), (string) $t2->getKey(), (string) $t3->getKey()],
            $userId,
        );

        $this->assertArrayHasKey((string) $t1->getKey(), $result);
        $this->assertArrayHasKey((string) $t3->getKey(), $result);
        $this->assertArrayNotHasKey((string) $t2->getKey(), $result);
    }

    public function test_find_template_ids_with_reviewer_returns_empty_for_unknown_user(): void
    {
        $t = $this->makeTemplate();
        $this->addReviewer($t, 'some-user');

        $result = $this->repo->findTemplateIdsWithReviewer([(string) $t->getKey()], 'unknown-user');

        $this->assertSame([], $result);
    }

    public function test_find_template_ids_with_reviewer_returns_empty_for_empty_list(): void
    {
        $result = $this->repo->findTemplateIdsWithReviewer([], 'any-user');

        $this->assertSame([], $result);
    }

    // -----------------------------------------------------------------------
    // existsReviewerForTemplate
    // -----------------------------------------------------------------------

    public function test_exists_reviewer_returns_true_when_reviewer_exists(): void
    {
        $userId = 'user-exists-01';
        $template = $this->makeTemplate();
        $this->addReviewer($template, $userId);

        $this->assertTrue($this->repo->existsReviewerForTemplate((string) $template->getKey(), $userId));
    }

    public function test_exists_reviewer_returns_false_when_reviewer_absent(): void
    {
        $template = $this->makeTemplate();

        $this->assertFalse($this->repo->existsReviewerForTemplate((string) $template->getKey(), 'ghost-user'));
    }

    // -----------------------------------------------------------------------
    // findReviewerForTemplate
    // -----------------------------------------------------------------------

    public function test_find_reviewer_returns_model_when_present(): void
    {
        $userId = 'user-find-01';
        $template = $this->makeTemplate();
        $this->addReviewer($template, $userId, 1, 'pending');

        $reviewer = $this->repo->findReviewerForTemplate((string) $template->getKey(), $userId);

        $this->assertNotNull($reviewer);
        $this->assertSame($userId, $reviewer->user_id);
        $this->assertSame(1, (int) $reviewer->stage);
    }

    public function test_find_reviewer_returns_null_when_absent(): void
    {
        $template = $this->makeTemplate();

        $result = $this->repo->findReviewerForTemplate((string) $template->getKey(), 'nobody');

        $this->assertNull($result);
    }

    // -----------------------------------------------------------------------
    // updateReviewerStatus
    // -----------------------------------------------------------------------

    public function test_update_reviewer_status_changes_status_in_db(): void
    {
        $userId = 'user-update-01';
        $template = $this->makeTemplate();
        $this->addReviewer($template, $userId, 1, 'pending');

        $this->repo->updateReviewerStatus((string) $template->getKey(), $userId, 'approved');

        $reviewer = TemplateReviewer::query()
            ->where('template_id', $template->getKey())
            ->where('user_id', $userId)
            ->first();

        $this->assertSame('approved', $reviewer->status);
    }

    // -----------------------------------------------------------------------
    // allReviewersApproved
    // -----------------------------------------------------------------------

    public function test_all_reviewers_approved_returns_true_when_all_approved(): void
    {
        $template = $this->makeTemplate();
        $this->addReviewer($template, 'user-a', 1, 'approved');
        $this->addReviewer($template, 'user-b', 2, 'approved');

        $this->assertTrue($this->repo->allReviewersApproved((string) $template->getKey()));
    }

    public function test_all_reviewers_approved_returns_false_when_one_pending(): void
    {
        $template = $this->makeTemplate();
        $this->addReviewer($template, 'user-a', 1, 'approved');
        $this->addReviewer($template, 'user-b', 2, 'pending');

        $this->assertFalse($this->repo->allReviewersApproved((string) $template->getKey()));
    }

    public function test_all_reviewers_approved_returns_true_when_no_reviewers(): void
    {
        $template = $this->makeTemplate();

        // No reviewers → no reviewer with status != 'approved' → true
        $this->assertTrue($this->repo->allReviewersApproved((string) $template->getKey()));
    }

    // -----------------------------------------------------------------------
    // replaceTemplateReviewers
    // -----------------------------------------------------------------------

    public function test_replace_template_reviewers_removes_old_and_inserts_new(): void
    {
        $template = $this->makeTemplate();
        $this->addReviewer($template, 'old-user', 1, 'approved');

        $this->repo->replaceTemplateReviewers((string) $template->getKey(), [
            ['user_id' => 'new-user-a', 'stage' => 1, 'status' => 'pending'],
            ['user_id' => 'new-user-b', 'stage' => 2, 'status' => 'pending'],
        ]);

        $reviewers = TemplateReviewer::query()
            ->where('template_id', $template->getKey())
            ->get();

        $this->assertCount(2, $reviewers);
        $userIds = $reviewers->pluck('user_id')->all();
        $this->assertContains('new-user-a', $userIds);
        $this->assertContains('new-user-b', $userIds);
        $this->assertNotContains('old-user', $userIds);
    }

    public function test_replace_template_reviewers_also_removes_soft_deleted_rows(): void
    {
        $template = $this->makeTemplate();
        // Create then soft-delete a reviewer row
        $reviewer = $this->addReviewer($template, 'soft-deleted-user', 1, 'pending');
        $reviewer->delete(); // soft delete

        // Ensure it's soft-deleted
        $this->assertSoftDeleted('template_reviewers', ['user_id' => 'soft-deleted-user']);

        $this->repo->replaceTemplateReviewers((string) $template->getKey(), [
            ['user_id' => 'fresh-user', 'stage' => 1, 'status' => 'pending'],
        ]);

        // The soft-deleted row should be physically gone (forceDelete)
        $this->assertDatabaseMissing('template_reviewers', ['user_id' => 'soft-deleted-user']);
        $this->assertDatabaseHas('template_reviewers', ['user_id' => 'fresh-user']);
    }

    public function test_replace_template_reviewers_with_empty_array_removes_all(): void
    {
        $template = $this->makeTemplate();
        $this->addReviewer($template, 'user-to-remove', 1, 'pending');

        $this->repo->replaceTemplateReviewers((string) $template->getKey(), []);

        $count = TemplateReviewer::query()
            ->where('template_id', $template->getKey())
            ->count();

        $this->assertSame(0, $count);
    }

    // -----------------------------------------------------------------------
    // replaceDocumentReviewers
    // -----------------------------------------------------------------------

    public function test_replace_document_reviewers_removes_old_and_inserts_new(): void
    {
        $template = $this->makeTemplate();
        $this->addDocumentReviewer($template, 'old-doc-user', 1);

        $this->repo->replaceDocumentReviewers((string) $template->getKey(), [
            ['user_id' => 'new-doc-user-x', 'stage' => 1],
            ['user_id' => 'new-doc-user-y', 'stage' => 2],
        ]);

        $rows = TemplateDocumentReviewer::query()
            ->where('template_id', $template->getKey())
            ->get();

        $this->assertCount(2, $rows);
        $userIds = $rows->pluck('user_id')->all();
        $this->assertContains('new-doc-user-x', $userIds);
        $this->assertNotContains('old-doc-user', $userIds);
    }

    // -----------------------------------------------------------------------
    // copyReviewersFromTemplate
    // -----------------------------------------------------------------------

    public function test_copy_reviewers_creates_reviewers_on_target_with_pending_status(): void
    {
        $source = $this->makeTemplate();
        $target = $this->makeTemplate();

        $this->addReviewer($source, 'reviewer-copy-a', 1, 'approved');
        $this->addReviewer($source, 'reviewer-copy-b', 2, 'approved');
        $this->addDocumentReviewer($source, 'doc-reviewer-copy', 1);

        $this->repo->copyReviewersFromTemplate($source, $target);

        $targetReviewers = TemplateReviewer::query()
            ->where('template_id', $target->getKey())
            ->get();

        $this->assertCount(2, $targetReviewers);
        foreach ($targetReviewers as $r) {
            $this->assertSame('pending', $r->status);
        }

        $userIds = $targetReviewers->pluck('user_id')->all();
        $this->assertContains('reviewer-copy-a', $userIds);
        $this->assertContains('reviewer-copy-b', $userIds);

        $docReviewers = TemplateDocumentReviewer::query()
            ->where('template_id', $target->getKey())
            ->get();

        $this->assertCount(1, $docReviewers);
        $this->assertSame('doc-reviewer-copy', $docReviewers->first()->user_id);
    }

    public function test_copy_reviewers_with_no_reviewers_creates_nothing(): void
    {
        $source = $this->makeTemplate();
        $target = $this->makeTemplate();

        $this->repo->copyReviewersFromTemplate($source, $target);

        $this->assertSame(0, TemplateReviewer::query()->where('template_id', $target->getKey())->count());
        $this->assertSame(0, TemplateDocumentReviewer::query()->where('template_id', $target->getKey())->count());
    }

    // -----------------------------------------------------------------------
    // templateHasBlocks
    // -----------------------------------------------------------------------

    public function test_template_has_blocks_returns_true_when_blocks_exist(): void
    {
        $template = $this->makeTemplate();
        $this->addBlock($template);

        $this->assertTrue($this->repo->templateHasBlocks((string) $template->getKey()));
    }

    public function test_template_has_blocks_returns_false_when_no_blocks(): void
    {
        $template = $this->makeTemplate();

        $this->assertFalse($this->repo->templateHasBlocks((string) $template->getKey()));
    }

    // -----------------------------------------------------------------------
    // loadBlocksForTemplate
    // -----------------------------------------------------------------------

    public function test_load_blocks_for_template_loads_blocks_ordered_by_sort_order(): void
    {
        $template = $this->makeTemplate();
        $this->addBlock($template, 2);
        $this->addBlock($template, 0);
        $this->addBlock($template, 1);

        $result = $this->repo->loadBlocksForTemplate($template);

        $this->assertSame($template, $result); // same instance returned
        $this->assertTrue($result->relationLoaded('blocks'));
        $this->assertCount(3, $result->blocks);

        $sortOrders = $result->blocks->pluck('sort_order')->all();
        $this->assertSame([0, 1, 2], array_map('intval', $sortOrders));
    }

    // -----------------------------------------------------------------------
    // loadRelationsForSnapshot
    // -----------------------------------------------------------------------

    public function test_load_relations_for_snapshot_loads_all_three_relations(): void
    {
        $template = $this->makeTemplate();
        $this->addBlock($template, 1);
        $this->addBlock($template, 0);
        $this->addReviewer($template, 'rev-snap-a', 1, 'pending');
        $this->addReviewer($template, 'rev-snap-b', 2, 'approved');
        $this->addDocumentReviewer($template, 'doc-snap-x', 1);

        $result = $this->repo->loadRelationsForSnapshot($template);

        $this->assertSame($template, $result);
        $this->assertTrue($result->relationLoaded('blocks'));
        $this->assertTrue($result->relationLoaded('reviewers'));
        $this->assertTrue($result->relationLoaded('documentReviewers'));
    }

    public function test_load_relations_for_snapshot_blocks_ordered_by_sort_order(): void
    {
        $template = $this->makeTemplate();
        $this->addBlock($template, 5);
        $this->addBlock($template, 2);

        $this->repo->loadRelationsForSnapshot($template);

        $sortOrders = $template->blocks->pluck('sort_order')->all();
        $this->assertSame([2, 5], array_map('intval', $sortOrders));
    }

    public function test_load_relations_for_snapshot_reviewers_ordered_by_stage_then_user_id(): void
    {
        $template = $this->makeTemplate();
        // Same stage → ordered by user_id alphabetically
        $this->addReviewer($template, 'zzz-user', 1, 'pending');
        $this->addReviewer($template, 'aaa-user', 1, 'pending');

        $this->repo->loadRelationsForSnapshot($template);

        $userIds = $template->reviewers->pluck('user_id')->all();
        $this->assertSame(['aaa-user', 'zzz-user'], $userIds);
    }

    public function test_load_relations_for_snapshot_document_reviewers_ordered_by_stage_then_user_id(): void
    {
        $template = $this->makeTemplate();
        $this->addDocumentReviewer($template, 'zzz-doc', 1);
        $this->addDocumentReviewer($template, 'aaa-doc', 1);

        $this->repo->loadRelationsForSnapshot($template);

        $userIds = $template->documentReviewers->pluck('user_id')->all();
        $this->assertSame(['aaa-doc', 'zzz-doc'], $userIds);
    }
}
