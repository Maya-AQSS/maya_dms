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

    DB::table('user_resolved_permissions')->insert([
        'user_id' => $this->userId,
        'permission_slug' => 'dms.login',
    ]);

    DB::table('processes')->insertOrIgnore([
        'id' => '00000000-0000-0000-0000-000000000001',
        'code' => 'DEFAULT_PROCESS',
        'name' => 'Proceso por defecto',
        'alias' => 'default',
    ]);
});

function grantDocumentPermission(string $slug): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id' => test()->userId,
        'permission_slug' => $slug,
    ]);
}

function seedForeignPublishedDocument(
    string $ownerId,
    string $visibilityLevel,
    string $status = 'published',
    ?string $createdBy = null,
): string {
    $templateId = (string) Str::uuid();
    $documentId = (string) Str::uuid();
    $headId = (string) Str::uuid();
    $publishedId = (string) Str::uuid();
    $studyTypeId = (string) Str::uuid();
    $authorId = $createdBy ?? $ownerId;

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla catálogo',
        'description' => null,
        'visibility_level' => $visibilityLevel,
        'delivery_deadline' => null,
        'study_type_id' => $studyTypeId,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => $ownerId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    $documentPayload = [
        'title' => 'Documento ajeno',
        'status' => $status,
        'created_by' => $authorId,
        'owner_id' => $ownerId,
        'study_type_id' => $studyTypeId,
    ];

    EntityVersion::query()->forceCreate([
        'id' => $headId,
        'versionable_type' => Document::class,
        'versionable_id' => $documentId,
        'version_number' => 0,
        'status' => $status,
        'snapshot_data' => ['document' => $documentPayload],
        'changelog' => 'head',
        'created_by' => $ownerId,
        'published_at' => $status === 'published' ? now() : null,
        'published_by' => $status === 'published' ? $ownerId : null,
    ]);

    if ($status === 'published') {
        EntityVersion::query()->forceCreate([
            'id' => $publishedId,
            'versionable_type' => Document::class,
            'versionable_id' => $documentId,
            'version_number' => 1,
            'status' => 'published',
            'snapshot_data' => ['document' => $documentPayload],
            'changelog' => 'v1',
            'created_by' => $ownerId,
            'published_at' => now(),
            'published_by' => $ownerId,
        ]);
    }

    Document::query()->forceCreate([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'title' => 'Documento ajeno',
        'study_type_id' => $studyTypeId,
        'created_by' => $authorId,
        'owner_id' => $ownerId,
        'status' => $status,
        'head_entity_version_id' => $headId,
    ]);

    return $documentId;
}

it('denies document index without document.index', function () {
    grantDocumentPermission('document.show');

    $this->getJson('/api/v1/documents')->assertForbidden();
});

it('allows document index with document.index', function () {
    grantDocumentPermission('document.index');

    $this->getJson('/api/v1/documents')->assertOk();
});

it('allows document show for owner without document.show', function () {
    grantDocumentPermission('document.index');

    $documentId = (string) Str::uuid();
    $templateId = (string) Str::uuid();

    DB::table('processes')->insertOrIgnore([
        'id' => '00000000-0000-0000-0000-000000000001',
        'code' => 'DEFAULT_PROCESS',
        'name' => 'Proceso por defecto',
        'alias' => 'default',
    ]);

    // Los metadatos (name/status/owner_id/…) viven en el snapshot del cabezal de
    // versión; el evento `creating` de los modelos migra estas columnas legacy.
    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla',
        'description' => null,
        'visibility_level' => 'personal',
        'delivery_deadline' => null,
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => test()->userId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'sequential',
    ]);

    Document::query()->forceCreate([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'title' => 'Mi documento',
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'delivery_deadline' => null,
        'created_by' => test()->userId,
        'owner_id' => test()->userId,
        'status' => 'draft',
    ]);

    $this->getJson("/api/v1/documents/{$documentId}")
        ->assertOk();
});

it('lists published global document from other owner without academic overlap', function () {
    grantDocumentPermission('document.index');

    $foreignId = seedForeignPublishedDocument(
        test()->otherUserId,
        TemplateVisibilityLevel::Global->value,
    );

    $ids = $this->getJson('/api/v1/documents')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($foreignId);
});

it('excludes foreign personal published document from index', function () {
    grantDocumentPermission('document.index');

    seedForeignPublishedDocument(
        test()->otherUserId,
        TemplateVisibilityLevel::Personal->value,
    );

    expect($this->getJson('/api/v1/documents')->assertOk()->json('data'))->toBe([]);
});

it('excludes foreign draft from index', function () {
    grantDocumentPermission('document.index');

    seedForeignPublishedDocument(
        test()->otherUserId,
        TemplateVisibilityLevel::Global->value,
        status: 'draft',
    );

    expect($this->getJson('/api/v1/documents')->assertOk()->json('data'))->toBe([]);
});

it('shows transferred published global document to former owner via catalog not as titular', function () {
    grantDocumentPermission('document.index');

    $formerOwnerId = (string) Str::uuid();
    $newOwnerId = test()->otherUserId;

    $documentId = seedForeignPublishedDocument(
        $newOwnerId,
        TemplateVisibilityLevel::Global->value,
        createdBy: $formerOwnerId,
    );

    $this->app['events']->listen(RouteMatched::class, function ($event) use ($formerOwnerId) {
        $event->request->attributes->set('jwt_user', ['id' => $formerOwnerId, 'sub' => $formerOwnerId]);
    });
    DB::table('user_resolved_permissions')->insert([
        ['user_id' => $formerOwnerId, 'permission_slug' => 'dms.login'],
        ['user_id' => $formerOwnerId, 'permission_slug' => 'document.index'],
    ]);

    $row = collect($this->getJson('/api/v1/documents')->assertOk()->json('data'))
        ->firstWhere('id', $documentId);

    expect($row)->not->toBeNull()
        ->and($row['owner_id'])->toBe($newOwnerId)
        ->and($row['created_by'])->toBe($formerOwnerId)
        ->and($row['can_create_new_version'])->toBeFalse()
        ->and($row['can_clone'])->toBeFalse();
});
