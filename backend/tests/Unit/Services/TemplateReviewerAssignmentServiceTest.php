<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\Templates\SyncUsersDto;
use App\Models\Template;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\ResolvedPermissionReaderInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\ReviewerAcademicScopeResolver;
use App\Services\TemplateReviewerAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for TemplateReviewerAssignmentService.
 *
 * Covers service-level branches that are unreachable via HTTP because the
 * FormRequest `distinct` rule catches duplicates before the service is called:
 *   - syncReviewers: duplicate userIds → ValidationException (lines 34-35)
 *   - syncReviewers: sequential mode + review_stages exceeded → ValidationException (lines 44-46)
 *   - syncDocumentReviewers: duplicate userIds → ValidationException (lines 77-78)
 *   - assertUsersHavePermission: user lacks permission → ValidationException (line 79)
 */
final class TemplateReviewerAssignmentServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Creates a Mockery mock of Template that returns specified attribute values.
     * This is needed because Template uses a delegated getAttribute that reads from
     * headVersion.snapshot_data — forceFill doesn't work for those attributes.
     */
    private function makeTemplate(string $reviewMode = 'parallel', int $reviewStages = 0): Template
    {
        /** @var Template&\Mockery\MockInterface $template */
        $template = Mockery::mock(Template::class)->makePartial();
        $template->shouldReceive('getAttribute')->with('review_mode')->andReturn($reviewMode);
        $template->shouldReceive('getAttribute')->with('review_stages')->andReturn($reviewStages);
        $template->shouldReceive('getAttribute')->with('id')->andReturn('tmpl-uuid');
        $template->shouldReceive('getAttribute')->with('visibility_level')->andReturn('personal');
        $template->shouldReceive('getAttribute')->with('study_type_id')->andReturn(null);
        $template->shouldReceive('getAttribute')->with('study_id')->andReturn(null);
        $template->shouldReceive('getAttribute')->with('module_id')->andReturn(null);
        $template->shouldReceive('getAttribute')->with('team_id')->andReturn(null);

        return $template;
    }

    private function makeService(
        TemplateRepositoryInterface $templateRepo,
        ResolvedPermissionReaderInterface $permRepo,
        ?ReviewerAcademicScopeResolver $scopeResolver = null,
        ?UserDirectoryRepositoryInterface $userDirectoryRepository = null,
    ): TemplateReviewerAssignmentService {
        $scopeResolver ??= Mockery::mock(ReviewerAcademicScopeResolver::class);
        $scopeResolver->shouldReceive('resolve')->andReturnNull()->byDefault();

        $userDirectoryRepository ??= Mockery::mock(UserDirectoryRepositoryInterface::class);
        $userDirectoryRepository->shouldReceive('filterUserIdsMatchingAcademicScope')->andReturn([])->byDefault();

        return new TemplateReviewerAssignmentService(
            $templateRepo,
            $permRepo,
            $scopeResolver,
            $userDirectoryRepository,
        );
    }

    /**
     * Wraps DB::transaction to immediately execute the callback.
     */
    private function fakeDbTransaction(): void
    {
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $cb) => $cb());
    }

    // ─── syncReviewers: duplicate userIds in the DTO ─────────────────────────

    public function test_sync_reviewers_throws_for_duplicate_user_ids_in_dto(): void
    {
        $this->fakeDbTransaction();

        $template = $this->makeTemplate();
        $tmplRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $tmplRepo->shouldReceive('findOrFail')->once()->with('tmpl-uuid')->andReturn($template);

        $permRepo = Mockery::mock(ResolvedPermissionReaderInterface::class);
        $permRepo->shouldNotReceive('findPermissionSlugsByUserId');

        $service = $this->makeService($tmplRepo, $permRepo);

        // Pass duplicate IDs directly in the DTO (bypassing FormRequest validation)
        $dto = new SyncUsersDto(userIds: ['user-a', 'user-a']);

        $this->expectException(ValidationException::class);

        $service->syncReviewers('tmpl-uuid', $dto);
    }

    // ─── syncReviewers: sequential mode + review_stages limit exceeded ────────

    public function test_sync_reviewers_throws_when_sequential_stage_limit_exceeded(): void
    {
        $this->fakeDbTransaction();

        $template = $this->makeTemplate('sequential', 1);
        $tmplRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $tmplRepo->shouldReceive('findOrFail')->once()->with('tmpl-uuid')->andReturn($template);

        $permRepo = Mockery::mock(ResolvedPermissionReaderInterface::class);
        $permRepo->shouldNotReceive('findPermissionSlugsByUserId');

        $service = $this->makeService($tmplRepo, $permRepo);

        // 2 unique reviewers but review_stages = 1
        $dto = new SyncUsersDto(userIds: ['user-a', 'user-b']);

        $this->expectException(ValidationException::class);

        $service->syncReviewers('tmpl-uuid', $dto);
    }

    // ─── syncReviewers: user lacks templates.review permission ───────────────

    public function test_sync_reviewers_throws_when_user_lacks_templates_review_permission(): void
    {
        $this->fakeDbTransaction();

        $template = $this->makeTemplate();
        $tmplRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $tmplRepo->shouldReceive('findOrFail')->once()->with('tmpl-uuid')->andReturn($template);

        $permRepo = Mockery::mock(ResolvedPermissionReaderInterface::class);
        $permRepo->shouldReceive('findPermissionSlugsByUserId')
            ->once()
            ->with('user-a')
            ->andReturn(['template.show']); // no templates.review

        $service = $this->makeService($tmplRepo, $permRepo);
        $dto     = new SyncUsersDto(userIds: ['user-a']);

        $this->expectException(ValidationException::class);

        $service->syncReviewers('tmpl-uuid', $dto);
    }

    // ─── syncDocumentReviewers: duplicate userIds in the DTO ─────────────────

    public function test_sync_document_reviewers_throws_for_duplicate_user_ids_in_dto(): void
    {
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $cb) => $cb());

        $template = $this->makeTemplate();
        $tmplRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $tmplRepo->shouldReceive('findOrFail')->once()->with('tmpl-uuid')->andReturn($template);

        $permRepo = Mockery::mock(ResolvedPermissionReaderInterface::class);
        $permRepo->shouldNotReceive('findPermissionSlugsByUserId');

        $service = $this->makeService($tmplRepo, $permRepo);
        $dto     = new SyncUsersDto(userIds: ['user-a', 'user-a']);

        $this->expectException(ValidationException::class);

        $service->syncDocumentReviewers('tmpl-uuid', $dto);
    }

    // ─── syncDocumentReviewers: user lacks documents.review permission ────────

    public function test_sync_document_reviewers_throws_when_user_lacks_documents_review_permission(): void
    {
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $cb) => $cb());

        $template = $this->makeTemplate();
        $tmplRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $tmplRepo->shouldReceive('findOrFail')->once()->with('tmpl-uuid')->andReturn($template);

        $permRepo = Mockery::mock(ResolvedPermissionReaderInterface::class);
        $permRepo->shouldReceive('findPermissionSlugsByUserId')
            ->once()
            ->with('user-a')
            ->andReturn(['document.show']); // no documents.review

        $service = $this->makeService($tmplRepo, $permRepo);
        $dto     = new SyncUsersDto(userIds: ['user-a']);

        $this->expectException(ValidationException::class);

        $service->syncDocumentReviewers('tmpl-uuid', $dto);
    }
}
