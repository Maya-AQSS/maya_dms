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

function grantDocumentVersionCloneHistoryPermissions(string ...$slugs): void
{
    foreach ($slugs as $slug) {
        DB::table('user_resolved_permissions')->insert([
            'user_id' => test()->userId,
            'permission_slug' => $slug,
        ]);
    }
}

function seedPublishedGlobalDocument(string $ownerId): string
{
    $templateId = (string) Str::uuid();
    $documentId = (string) Str::uuid();
    $headId = (string) Str::uuid();
    $publishedId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla',
        'description' => null,
        'visibility_level' => TemplateVisibilityLevel::Global->value,
        'delivery_deadline' => now()->addWeek(),
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => $ownerId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    Document::query()->forceCreate([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'title' => 'Programación publicada',
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'delivery_deadline' => now()->addWeek()->toDateString(),
        'created_by' => $ownerId,
        'owner_id' => $ownerId,
        'status' => 'published',
        'head_entity_version_id' => $headId,
    ]);

    EntityVersion::query()->forceCreate([
        'id' => $headId,
        'versionable_type' => Document::class,
        'versionable_id' => $documentId,
        'version_number' => 0,
        'status' => 'published',
        'snapshot_data' => [
            'document' => [
                'title' => 'Programación publicada',
                'status' => 'published',
                'created_by' => $ownerId,
                'owner_id' => $ownerId,
            ],
        ],
        'changelog' => 'head',
        'published_at' => now(),
        'published_by' => $ownerId,
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
                'created_by' => $ownerId,
                'owner_id' => $ownerId,
            ],
        ],
        'changelog' => 'v1',
        'published_at' => now(),
        'published_by' => $ownerId,
    ]);

    return $documentId;
}

it('denies new-version on foreign published document without document.version', function () {
    $documentId = seedPublishedGlobalDocument(test()->otherUserId);

    $this->postJson("/api/v1/documents/{$documentId}/new-version")
        ->assertForbidden();
});

it('allows new-version on foreign published document with document.version', function () {
    grantDocumentVersionCloneHistoryPermissions('document.version');

    $documentId = seedPublishedGlobalDocument(test()->otherUserId);

    $this->postJson("/api/v1/documents/{$documentId}/new-version")
        ->assertOk();
});

it('denies clone on foreign published without document.clone', function () {
    grantDocumentVersionCloneHistoryPermissions('document.update');

    $documentId = seedPublishedGlobalDocument(test()->otherUserId);

    $this->postJson("/api/v1/documents/{$documentId}/clone")
        ->assertForbidden();
});

it('allows clone on foreign published with document.clone and document.update', function () {
    grantDocumentVersionCloneHistoryPermissions('document.clone', 'document.update');

    $documentId = seedPublishedGlobalDocument(test()->otherUserId);

    $this->postJson("/api/v1/documents/{$documentId}/clone")
        ->assertCreated();
});

it('allows titular to clone own published without document.clone slug', function () {
    $documentId = seedPublishedGlobalDocument(test()->userId);

    $this->postJson("/api/v1/documents/{$documentId}/clone")
        ->assertCreated();
});

it('denies versions list without document.history.view for non-titular', function () {
    $documentId = seedPublishedGlobalDocument(test()->otherUserId);

    $this->getJson("/api/v1/documents/{$documentId}/versions")
        ->assertNotFound();
});

it('allows versions list for non-titular with document.history.view', function () {
    grantDocumentVersionCloneHistoryPermissions('document.history.view');

    $documentId = seedPublishedGlobalDocument(test()->otherUserId);

    $this->getJson("/api/v1/documents/{$documentId}/versions")
        ->assertOk();
});

it('allows versions list for titular without document.history.view', function () {
    $documentId = seedPublishedGlobalDocument(test()->userId);

    $this->getJson("/api/v1/documents/{$documentId}/versions")
        ->assertOk();
});
