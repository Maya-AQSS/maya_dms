<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\TemplateVisibilityLevel;
use App\Models\JwtUser;
use App\Models\Template;
use App\Policies\TemplatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TemplatePolicyTest extends TestCase
{
    use RefreshDatabase;

    private TemplatePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = app(TemplatePolicy::class);
    }

    public function test_assign_review_on_personal_allows_creator_in_draft(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $creator = $this->makeJwtUser($creatorId);
        $other = $this->makeJwtUser('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', ['template.assign-review']);

        // Los atributos delegados (status/visibility_level) se leen del snapshot del
        // cabezal, por lo que la plantilla debe persistirse en lugar de un forceFill transitorio.
        $template = $this->makeTemplate(
            createdBy: $creatorId,
            status: 'draft',
            visibilityLevel: TemplateVisibilityLevel::Personal->value,
        );

        $this->assertTrue($this->policy->assignReview($creator, $template));
        $this->assertFalse($this->policy->assignReview($other, $template));
    }

    public function test_assign_review_on_global_requires_assign_review_slug(): void
    {
        $creatorId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $coordinator = $this->makeJwtUser('dddddddd-dddd-dddd-dddd-dddddddddddd', ['template.assign-review']);
        $teacher = $this->makeJwtUser($creatorId);

        $template = $this->makeTemplate(
            createdBy: $creatorId,
            status: 'draft',
            visibilityLevel: TemplateVisibilityLevel::Global->value,
        );

        $this->assertTrue($this->policy->assignReview($coordinator, $template));
        $this->assertFalse($this->policy->assignReview($teacher, $template));
    }

    public function test_review_requires_template_review_and_assignment(): void
    {
        $reviewerId = 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee';
        $assigned = $this->makeJwtUser($reviewerId, ['template.review']);
        $notAssigned = $this->makeJwtUser($reviewerId);

        // Persistida para satisfacer la FK de template_reviewers.template_id.
        $template = $this->makeTemplate(
            createdBy: 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            status: 'in_review',
            visibilityLevel: TemplateVisibilityLevel::Global->value,
        );
        $templateId = (string) $template->id;

        DB::table('template_reviewers')->insert([
            'id' => (string) Str::uuid(),
            'template_id' => $templateId,
            'user_id' => $reviewerId,
            'stage' => 1,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->policy->review($assigned, $template));
        $this->assertFalse($this->policy->review($notAssigned, $template));
    }

    public function test_view_any_requires_template_index(): void
    {
        $sin = $this->makeJwtUser('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $soloShow = $this->makeJwtUser('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', ['template.show']);
        $conIndex = $this->makeJwtUser('cccccccc-cccc-cccc-cccc-cccccccccccc', ['template.index']);

        $this->assertFalse($this->policy->viewAny($sin));
        $this->assertFalse($this->policy->viewAny($soloShow));
        $this->assertTrue($this->policy->viewAny($conIndex));
    }

    public function test_view_requires_templates_read_or_documents_create_for_transient_model(): void
    {
        $creatorId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $sin = $this->makeJwtUser('dddddddd-dddd-dddd-dddd-dddddddddddd');
        $conRead = $this->makeJwtUser('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee', ['template.show']);
        $conDoc = $this->makeJwtUser('ffffffff-ffff-ffff-ffff-ffffffffffff', ['document.create']);
        $template = new Template;

        $this->assertFalse($this->policy->view($sin, $template));
        $this->assertTrue($this->policy->view($conRead, $template));
        $this->assertTrue($this->policy->view($conDoc, $template));
    }

    public function test_view_denied_for_template_delete_outside_academic_context_even_with_show(): void
    {
        auth()->setUser($this->makeJwtUser(
            'dddddddd-dddd-dddd-dddd-dddddddddddd',
            ['template.delete', 'template.show'],
        ));
        $template = $this->makeTemplate(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            status: 'published',
            visibilityLevel: TemplateVisibilityLevel::Personal->value,
        );
        $user = $this->makeJwtUser(
            'dddddddd-dddd-dddd-dddd-dddddddddddd',
            ['template.delete', 'template.show'],
        );

        $this->assertFalse($this->policy->view($user, $template));
    }

    public function test_view_denied_for_foreign_personal_template_with_published_snapshot(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $peerId = 'dddddddd-dddd-dddd-dddd-dddddddddddd';
        auth()->setUser($this->makeJwtUser($peerId, ['template.show']));
        $template = $this->makeTemplate(
            createdBy: $creatorId,
            status: 'published',
            visibilityLevel: TemplateVisibilityLevel::Personal->value,
        );

        DB::table('entity_versions')->insert([
            'id' => (string) Str::uuid(),
            'versionable_type' => Template::class,
            'versionable_id' => $template->id,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $creatorId,
            'published_by' => $creatorId,
            'published_at' => now(),
            'changelog' => 'v1',
            'snapshot_data' => json_encode(['template' => ['id' => $template->id]], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $peer = $this->makeJwtUser($peerId, ['template.show']);

        $this->assertFalse($this->policy->view($peer, $template));
    }

    public function test_creator_without_templates_review_permission_cannot_review_template(): void
    {
        $creatorId = '11111111-1111-1111-1111-111111111111';
        $user = $this->makeJwtUser($creatorId);
        $template = $this->makeTemplate(createdBy: $creatorId);

        $this->assertFalse($this->policy->review($user, $template));
    }

    // Los casos "revisor asignado puede revisar" y "usuario no asignado no puede revisar"
    // requieren consulta a BD (template_reviewers) y están cubiertos por los feature tests
    // TemplatesApiTest::test_template_review_flow_creates_snapshot_and_history y
    // TemplatesApiTest::test_template_reject_review_returns_to_draft.

    public function test_create_defaults_to_personal_and_allows_any_authenticated_user(): void
    {
        $user = $this->makeJwtUser('cccccccc-cccc-cccc-cccc-cccccccccccc');

        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->create($user, TemplateVisibilityLevel::Personal->value));
    }

    public function test_create_shared_visibility_denied_without_templates_create(): void
    {
        $user = $this->makeJwtUser('dddddddd-dddd-dddd-dddd-dddddddddddd');

        $this->assertFalse($this->policy->create($user, TemplateVisibilityLevel::Global->value));
    }

    public function test_create_shared_visibility_allowed_with_templates_create(): void
    {
        $user = $this->makeJwtUser('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee', ['template.create']);

        $this->assertTrue($this->policy->create($user, TemplateVisibilityLevel::Global->value));
    }

    public function test_update_denied_for_non_creator_without_privileged_role(): void
    {
        $user = $this->makeJwtUser('ffffffff-ffff-ffff-ffff-ffffffffffff');
        $template = $this->makeTemplate(createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $this->assertFalse($this->policy->update($user, $template));
    }

    public function test_update_denied_for_coordinator_on_foreign_template(): void
    {
        $user = $this->makeJwtUser('11111111-2222-3333-4444-555555555555', ['template.update']);
        $template = $this->makeTemplate(createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $this->assertFalse($this->policy->update($user, $template));
    }

    public function test_update_allows_templates_update_on_foreign_published_when_user_can_view(): void
    {
        $user = $this->makeJwtUser(
            '11111111-2222-3333-4444-555555555555',
            ['template.show', 'template.update'],
        );
        auth()->setUser($user);
        $template = $this->makeTemplate(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            status: 'published',
            visibilityLevel: TemplateVisibilityLevel::Global->value,
        );
        $this->seedPublishedTemplateSnapshot($template, 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $this->assertTrue($this->policy->update($user, $template));
    }

    public function test_update_denied_on_foreign_published_without_templates_update(): void
    {
        $user = $this->makeJwtUser(
            '11111111-2222-3333-4444-555555555555',
            ['template.show'],
        );
        auth()->setUser($user);
        $template = $this->makeTemplate(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            status: 'published',
        );

        $this->assertFalse($this->policy->update($user, $template));
    }

    public function test_update_denied_on_foreign_published_without_templates_read(): void
    {
        $user = $this->makeJwtUser(
            '11111111-2222-3333-4444-555555555555',
            ['template.update'],
        );
        $template = $this->makeTemplate(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            status: 'published',
        );

        $this->assertFalse($this->policy->update($user, $template));
    }

    public function test_update_with_target_shared_visibility_denied_without_role(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $user = $this->makeJwtUser($creatorId);
        $template = $this->makeTemplate(createdBy: $creatorId);

        $this->assertFalse($this->policy->update($user, $template, TemplateVisibilityLevel::Study->value));
    }

    public function test_delete_non_creator_requires_templates_delete(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $creator = $this->makeJwtUser($creatorId);
        $sinPermisos = $this->makeJwtUser('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');
        $conDelete = $this->makeJwtUser('cccccccc-cccc-cccc-cccc-cccccccccccc', ['template.delete']);

        $t1 = $this->makeTemplate(createdBy: $creatorId);
        $t2 = $this->makeTemplate(createdBy: $creatorId);
        $t3 = $this->makeTemplate(createdBy: $creatorId);

        $this->assertTrue($this->policy->delete($creator, $t1));
        $this->assertTrue($this->policy->delete($conDelete, $t2));
        $this->assertFalse($this->policy->delete($sinPermisos, $t3));
    }

    public function test_update_denied_for_user_with_only_templates_delete(): void
    {
        $user = $this->makeJwtUser('dddddddd-dddd-dddd-dddd-dddddddddddd', ['template.delete']);
        $template = $this->makeTemplate(createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $this->assertFalse($this->policy->update($user, $template));
    }

    public function test_start_revision_denied_when_not_published(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $user = $this->makeJwtUser($creatorId, ['template.show']);
        $template = $this->makeTemplate(createdBy: $creatorId, status: 'draft');

        $this->assertFalse($this->policy->startRevision($user, $template));
    }

    public function test_start_revision_allows_creator_when_published_and_can_view(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $user = $this->makeJwtUser($creatorId, ['template.show']);
        $template = $this->makeTemplate(createdBy: $creatorId, status: 'published');

        $this->assertTrue($this->policy->startRevision($user, $template));
    }

    public function test_start_revision_allows_template_version_on_foreign_published_when_user_can_view(): void
    {
        $user = $this->makeJwtUser(
            '11111111-2222-3333-4444-555555555555',
            ['template.show', 'template.version'],
        );
        auth()->setUser($user);
        $template = $this->makeTemplate(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            status: 'published',
            visibilityLevel: TemplateVisibilityLevel::Global->value,
        );
        $this->seedPublishedTemplateSnapshot($template, 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $this->assertTrue($this->policy->startRevision($user, $template));
    }

    public function test_start_revision_denied_on_foreign_published_without_template_version(): void
    {
        $user = $this->makeJwtUser(
            '11111111-2222-3333-4444-555555555555',
            ['template.show', 'template.update'],
        );
        auth()->setUser($user);
        $template = $this->makeTemplate(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            status: 'published',
        );

        $this->assertFalse($this->policy->startRevision($user, $template));
    }

    public function test_start_revision_denied_on_foreign_published_without_view(): void
    {
        $user = $this->makeJwtUser(
            '11111111-2222-3333-4444-555555555555',
            ['template.version'],
        );
        $template = $this->makeTemplate(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            status: 'published',
        );

        $this->assertFalse($this->policy->startRevision($user, $template));
    }

    public function test_clone_allows_creator_on_published_personal(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $creator = $this->makeJwtUser($creatorId);
        $template = $this->makeTemplate(createdBy: $creatorId, status: 'published');
        $this->seedPublishedTemplateSnapshot($template, $creatorId);

        $this->assertTrue($this->policy->clone($creator, $template));
    }

    public function test_clone_requires_template_clone_for_non_creator(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $withSlug = $this->makeJwtUser(
            '11111111-2222-3333-4444-555555555555',
            ['template.show', 'template.create', 'template.clone'],
        );
        auth()->setUser($withSlug);
        $template = $this->makeTemplate(
            createdBy: $creatorId,
            status: 'published',
            visibilityLevel: TemplateVisibilityLevel::Global->value,
        );
        $this->seedPublishedTemplateSnapshot($template, $creatorId);

        $this->assertTrue($this->policy->clone($withSlug, $template));

        $withoutClone = $this->makeJwtUser(
            '22222222-3333-4444-5555-666666666666',
            ['template.show', 'template.create'],
        );
        auth()->setUser($withoutClone);
        $this->assertFalse($this->policy->clone($withoutClone, $template));
    }

    public function test_clone_allows_creator_on_unpublished_draft(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $creator = $this->makeJwtUser($creatorId);
        $template = $this->makeTemplate(createdBy: $creatorId, status: 'draft');

        $this->assertTrue($this->policy->clone($creator, $template));
    }

    public function test_view_history_allows_creator_without_slug(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $creator = $this->makeJwtUser($creatorId, ['template.show']);
        $template = $this->makeTemplate(createdBy: $creatorId, status: 'published');

        $this->assertTrue($this->policy->viewHistory($creator, $template));
    }

    public function test_view_history_requires_slug_for_non_creator(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $viewer = $this->makeJwtUser(
            '11111111-2222-3333-4444-555555555555',
            ['template.show', 'template.history.view'],
        );
        auth()->setUser($viewer);
        $template = $this->makeTemplate(
            createdBy: $creatorId,
            status: 'published',
            visibilityLevel: TemplateVisibilityLevel::Global->value,
        );
        $this->seedPublishedTemplateSnapshot($template, $creatorId);

        $this->assertTrue($this->policy->viewHistory($viewer, $template));

        $noSlug = $this->makeJwtUser(
            '22222222-3333-4444-5555-666666666666',
            ['template.show'],
        );
        auth()->setUser($noSlug);
        $this->assertFalse($this->policy->viewHistory($noSlug, $template));
    }

    public function test_publish_allows_creator_without_reviewers_even_if_non_personal(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $user = $this->makeJwtUser($creatorId);
        $template = $this->makeTemplate(
            createdBy: $creatorId,
            status: 'draft',
            visibilityLevel: TemplateVisibilityLevel::Global->value,
        );

        $this->assertTrue($this->policy->publish($user, $template));
    }

    public function test_publish_denied_for_creator_when_template_has_reviewers(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $reviewerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $user = $this->makeJwtUser($creatorId);
        $template = $this->makeTemplate(
            createdBy: $creatorId,
            status: 'in_review',
        );
        $template->reviewers()->create([
            'user_id' => $reviewerId,
            'stage' => 1,
            'status' => 'pending',
        ]);

        $this->assertFalse($this->policy->publish($user, $template));
    }

    public function test_submit_for_review_allowed_for_creator_on_draft(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $user = $this->makeJwtUser($creatorId);
        $template = $this->makeTemplate(createdBy: $creatorId, status: 'draft');

        $this->assertTrue($this->policy->submitForReview($user, $template));
    }

    public function test_submit_for_review_denied_for_creator_on_in_review(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $user = $this->makeJwtUser($creatorId);
        $template = $this->makeTemplate(createdBy: $creatorId, status: 'in_review');

        $this->assertFalse($this->policy->submitForReview($user, $template));
    }

    public function test_submit_for_review_denied_for_creator_on_published(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $user = $this->makeJwtUser($creatorId);
        $template = $this->makeTemplate(createdBy: $creatorId, status: 'published');

        $this->assertFalse($this->policy->submitForReview($user, $template));
    }

    public function test_submit_for_review_denied_for_non_creator_even_on_draft(): void
    {
        $user = $this->makeJwtUser('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');
        $template = $this->makeTemplate(createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', status: 'draft');

        $this->assertFalse($this->policy->submitForReview($user, $template));
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

    /**
     * Plantilla persistida con cabezal (metadatos en entity_versions), como en producción.
     *
     * @param  non-empty-string|null  $visibilityLevel  Valor de {@see TemplateVisibilityLevel}.
     */
    private function makeTemplate(
        string $createdBy,
        string $status = 'draft',
        ?string $visibilityLevel = null,
    ): Template {
        $visibilityLevel ??= TemplateVisibilityLevel::Personal->value;

        $template = Template::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Plantilla unit policy',
            'description' => null,
            'visibility_level' => $visibilityLevel,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $createdBy,
            'status' => $status,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);
        $template->refresh();

        return $template;
    }

    /**
     * Inserta un snapshot publicado (version_number > 0) de la plantilla. Necesario
     * para `clone` (exige snapshot publicado) y para la visibilidad de catálogo del
     * scope `user_access` (una plantilla global publicada es visible para no creadores).
     */
    private function seedPublishedTemplateSnapshot(Template $template, string $createdBy): void
    {
        DB::table('entity_versions')->insert([
            'id' => (string) Str::uuid(),
            'versionable_type' => Template::class,
            'versionable_id' => $template->id,
            'version_number' => 1,
            'status' => 'published',
            'is_snapshot_immutable' => 1,
            'created_by' => $createdBy,
            'published_by' => $createdBy,
            'published_at' => now(),
            'changelog' => 'v1',
            'snapshot_data' => json_encode(['template' => ['id' => $template->id]], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
