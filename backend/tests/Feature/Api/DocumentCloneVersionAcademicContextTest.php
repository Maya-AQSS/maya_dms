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

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    foreach (['dms.login', 'document.index', 'document.show', 'document.create'] as $slug) {
        DB::table('user_resolved_permissions')->insert([
            'user_id' => $this->userId,
            'permission_slug' => $slug,
        ]);
    }
});

function grantCloneVersionPermissions(string ...$slugs): void
{
    foreach ($slugs as $slug) {
        DB::table('user_resolved_permissions')->insert([
            'user_id' => test()->userId,
            'permission_slug' => $slug,
        ]);
    }
}

function seedCrossContextPublishedDocument(string $ownerId): array
{
    $templateId = (string) Str::uuid();
    $documentId = (string) Str::uuid();
    $headId = (string) Str::uuid();
    $publishedId = (string) Str::uuid();
    $studyTypeId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla global',
        'description' => null,
        'visibility_level' => TemplateVisibilityLevel::Global->value,
        'delivery_deadline' => null,
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => $ownerId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    $payload = [
        'title' => 'Publicado ajeno',
        'status' => 'published',
        'created_by' => $ownerId,
        'owner_id' => $ownerId,
        'study_type_id' => $studyTypeId,
        'delivery_deadline' => now()->addWeek()->toDateString(),
    ];

    EntityVersion::query()->forceCreate([
        'id' => $headId,
        'versionable_type' => Document::class,
        'versionable_id' => $documentId,
        'version_number' => 0,
        'status' => 'published',
        'snapshot_data' => ['document' => $payload],
        'changelog' => 'head',
        'created_by' => $ownerId,
        'published_at' => now(),
        'published_by' => $ownerId,
    ]);

    EntityVersion::query()->forceCreate([
        'id' => $publishedId,
        'versionable_type' => Document::class,
        'versionable_id' => $documentId,
        'version_number' => 1,
        'status' => 'published',
        'snapshot_data' => ['document' => $payload],
        'changelog' => 'v1',
        'created_by' => $ownerId,
        'published_at' => now(),
        'published_by' => $ownerId,
    ]);

    Document::query()->forceCreate([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'title' => 'Publicado ajeno',
        'study_type_id' => $studyTypeId,
        'delivery_deadline' => now()->addWeek()->toDateString(),
        'created_by' => $ownerId,
        'owner_id' => $ownerId,
        'status' => 'published',
        'head_entity_version_id' => $headId,
    ]);

    return ['document_id' => $documentId, 'study_type_id' => $studyTypeId];
}

it('allows show but denies clone and new-version without academic overlap', function () {
    grantCloneVersionPermissions('document.clone', 'document.update', 'document.version');

    ['document_id' => $documentId] = seedCrossContextPublishedDocument(test()->otherUserId);

    $this->getJson("/api/v1/documents/{$documentId}")
        ->assertOk();

    $this->postJson("/api/v1/documents/{$documentId}/clone")
        ->assertForbidden();

    $this->postJson("/api/v1/documents/{$documentId}/new-version")
        ->assertForbidden();
});

it('allows clone when user shares academic context via profile', function () {
    grantCloneVersionPermissions('document.clone', 'document.update', 'document.version');

    ['document_id' => $documentId, 'study_type_id' => $studyTypeId] = seedCrossContextPublishedDocument(test()->otherUserId);

    DB::table('user_study_types')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => test()->userId,
        'study_type_id' => $studyTypeId,
    ]);

    $this->postJson("/api/v1/documents/{$documentId}/clone")
        ->assertCreated();
});

it('allows new-version when user shares academic context via profile', function () {
    grantCloneVersionPermissions('document.clone', 'document.update', 'document.version');

    ['document_id' => $documentId, 'study_type_id' => $studyTypeId] = seedCrossContextPublishedDocument(test()->otherUserId);

    DB::table('user_study_types')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => test()->userId,
        'study_type_id' => $studyTypeId,
    ]);

    $this->postJson("/api/v1/documents/{$documentId}/new-version")
        ->assertOk();
});

it('exposes can_clone false in index for cross-context published document', function () {
    grantCloneVersionPermissions('document.clone', 'document.update', 'document.version');

    ['document_id' => $documentId] = seedCrossContextPublishedDocument(test()->otherUserId);

    $row = collect($this->getJson('/api/v1/documents')->assertOk()->json('data'))
        ->firstWhere('id', $documentId);

    expect($row)->not->toBeNull()
        ->and($row['can_clone'])->toBeFalse()
        ->and($row['can_create_new_version'])->toBeFalse();
});

it('exposes can_clone true in index when profile overlaps academic context', function () {
    grantCloneVersionPermissions('document.clone', 'document.update', 'document.version');

    ['document_id' => $documentId, 'study_type_id' => $studyTypeId] = seedCrossContextPublishedDocument(test()->otherUserId);

    DB::table('user_study_types')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => test()->userId,
        'study_type_id' => $studyTypeId,
    ]);

    $row = collect($this->getJson('/api/v1/documents')->assertOk()->json('data'))
        ->firstWhere('id', $documentId);

    expect($row)->not->toBeNull()
        ->and($row['can_clone'])->toBeTrue()
        ->and($row['can_create_new_version'])->toBeTrue();
});
