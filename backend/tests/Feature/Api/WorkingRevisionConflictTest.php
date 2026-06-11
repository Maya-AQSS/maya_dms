<?php

declare(strict_types=1);

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\EntityVersion;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maya\Auth\Middleware\JwtMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    $this->withoutMiddleware([JwtMiddleware::class]);

    $this->userId = (string) Str::uuid();
    $this->otherUserId = (string) Str::uuid();

    DB::table('users')->insert([
        [
            'id' => $this->otherUserId,
            'name' => 'Editor Ajeno',
            'email' => 'editor@test.local',
            'is_active' => true,
        ],
    ]);

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    foreach (['dms.login', 'template.index', 'template.show', 'document.index', 'document.show'] as $slug) {
        DB::table('user_resolved_permissions')->insert([
            'user_id' => $this->userId,
            'permission_slug' => $slug,
        ]);
    }

    DB::table('user_resolved_permissions')->insert([
        'user_id' => $this->userId,
        'permission_slug' => 'template.version',
    ]);
    DB::table('user_resolved_permissions')->insert([
        'user_id' => $this->userId,
        'permission_slug' => 'document.version',
    ]);
});

function seedTemplateWithDraftRevisionInProgress(string $creatorId): string
{
    $templateId = (string) Str::uuid();
    $headId = (string) Str::uuid();
    $publishedId = (string) Str::uuid();
    $now = now();

    EntityVersion::query()->forceCreate([
        'id' => $headId,
        'versionable_type' => Template::class,
        'versionable_id' => $templateId,
        'version_number' => 0,
        'status' => 'draft',
        'snapshot_data' => [
            'template' => [
                'name' => 'Plantilla en revisión',
                'status' => 'draft',
                'created_by' => $creatorId,
                'visibility_level' => TemplateVisibilityLevel::Global->value,
                'delivery_deadline' => $now->copy()->addWeek()->toDateTimeString(),
                'review_stages' => 0,
                'review_mode' => 'parallel',
            ],
        ],
        'created_by' => $creatorId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    EntityVersion::query()->forceCreate([
        'id' => $publishedId,
        'versionable_type' => Template::class,
        'versionable_id' => $templateId,
        'version_number' => 1,
        'status' => 'published',
        'snapshot_data' => ['template' => ['name' => 'Plantilla publicada', 'status' => 'published']],
        'changelog' => 'v1',
        'created_by' => $creatorId,
        'published_at' => $now,
        'published_by' => $creatorId,
    ]);

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'head_entity_version_id' => $headId,
    ]);

    return $templateId;
}

function seedDocumentWithDraftRevisionInProgress(string $creatorId): string
{
    $templateId = (string) Str::uuid();
    $documentId = (string) Str::uuid();
    $headId = (string) Str::uuid();
    $publishedId = (string) Str::uuid();
    $studyTypeId = (string) Str::uuid();
    $now = now();

    DB::table('user_study_types')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => test()->userId,
        'study_type_id' => $studyTypeId,
    ]);

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla',
        'description' => null,
        'visibility_level' => TemplateVisibilityLevel::Global->value,
        'delivery_deadline' => $now->copy()->addWeek(),
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => $creatorId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    EntityVersion::query()->forceCreate([
        'id' => $headId,
        'versionable_type' => Document::class,
        'versionable_id' => $documentId,
        'version_number' => 0,
        'status' => 'draft',
        'snapshot_data' => [
            'document' => [
                'title' => 'Programación en revisión',
                'status' => 'draft',
                'created_by' => $creatorId,
                'owner_id' => $creatorId,
                'study_type_id' => $studyTypeId,
                'delivery_deadline' => $now->copy()->addWeek()->toDateString(),
            ],
        ],
        'created_by' => $creatorId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    EntityVersion::query()->forceCreate([
        'id' => $publishedId,
        'versionable_type' => Document::class,
        'versionable_id' => $documentId,
        'version_number' => 1,
        'status' => 'published',
        'snapshot_data' => [
            'document' => [
                'title' => 'Programación publicada',
                'status' => 'published',
                'created_by' => $creatorId,
                'owner_id' => $creatorId,
                'study_type_id' => $studyTypeId,
                'delivery_deadline' => $now->copy()->addWeek()->toDateString(),
            ],
        ],
        'changelog' => 'v1',
        'created_by' => $creatorId,
        'published_at' => $now,
        'published_by' => $creatorId,
    ]);

    Document::query()->forceCreate([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'title' => 'Programación publicada',
        'study_type_id' => $studyTypeId,
        'study_id' => null,
        'module_id' => null,
        'delivery_deadline' => $now->copy()->addWeek()->toDateString(),
        'created_by' => $creatorId,
        'owner_id' => $creatorId,
        'status' => 'published',
        'head_entity_version_id' => $headId,
    ]);

    return $documentId;
}

it('returns structured 409 when template already has a working revision', function () {
    $templateId = seedTemplateWithDraftRevisionInProgress(test()->otherUserId);

    $response = $this->postJson("/api/v1/templates/{$templateId}/new-version");

    $response
        ->assertStatus(409)
        ->assertJsonPath('code', 'working_revision_in_progress')
        ->assertJsonPath('draft_author', 'Editor Ajeno')
        ->assertJsonStructure(['started_at']);
});

it('exposes working revision meta on template show while serving published overlay', function () {
    $templateId = seedTemplateWithDraftRevisionInProgress(test()->otherUserId);

    $response = $this->getJson("/api/v1/templates/{$templateId}");

    $response
        ->assertOk()
        ->assertJsonPath('data.working_revision_in_progress', true)
        ->assertJsonPath('data.working_revision_editor_name', 'Editor Ajeno')
        ->assertJsonPath('data.status', 'published');
});

it('exposes version capability flags on template show', function () {
    $templateId = seedTemplateWithDraftRevisionInProgress(test()->otherUserId);

    DB::table('user_resolved_permissions')->insert([
        'user_id' => test()->userId,
        'permission_slug' => 'template.history.view',
    ]);

    $response = $this->getJson("/api/v1/templates/{$templateId}");

    $response
        ->assertOk()
        ->assertJsonPath('data.can_create_new_version', true)
        ->assertJsonPath('data.can_view_history', true);
});

it('returns structured 409 when document already has a working revision', function () {
    $documentId = seedDocumentWithDraftRevisionInProgress(test()->otherUserId);

    $response = $this->postJson("/api/v1/documents/{$documentId}/new-version");

    $response
        ->assertStatus(409)
        ->assertJsonPath('code', 'working_revision_in_progress')
        ->assertJsonPath('draft_author', 'Editor Ajeno')
        ->assertJsonStructure(['started_at']);
});

it('exposes working revision meta on document show while serving published overlay', function () {
    $documentId = seedDocumentWithDraftRevisionInProgress(test()->otherUserId);

    $response = $this->getJson("/api/v1/documents/{$documentId}");

    $response
        ->assertOk()
        ->assertJsonPath('data.working_revision_in_progress', true)
        ->assertJsonPath('data.working_revision_editor_name', 'Editor Ajeno')
        ->assertJsonPath('data.status', 'published');
});

it('exposes version capability flags on document show', function () {
    $documentId = seedDocumentWithDraftRevisionInProgress(test()->otherUserId);

    DB::table('user_resolved_permissions')->insert([
        'user_id' => test()->userId,
        'permission_slug' => 'document.history.view',
    ]);

    $response = $this->getJson("/api/v1/documents/{$documentId}");

    $response
        ->assertOk()
        ->assertJsonPath('data.can_create_new_version', true)
        ->assertJsonPath('data.can_view_history', true);
});
