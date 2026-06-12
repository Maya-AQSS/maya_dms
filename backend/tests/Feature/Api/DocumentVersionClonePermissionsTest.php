<?php

declare(strict_types=1);

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Models\TemplateBlock;
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

    // Contexto académico para que la regla "catálogo publicado" del scope user_access
    // haga visible el documento ajeno al usuario actuante (matriculado en el mismo tipo de estudio).
    $studyTypeId = (string) Str::uuid();
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

    // Las versiones se crean antes que el documento: documents.head_entity_version_id
    // tiene FK a entity_versions, y entity_versions.versionable_id es polimórfico (sin FK).
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
                'study_type_id' => $studyTypeId,
                'delivery_deadline' => now()->addWeek()->toDateString(),
            ],
        ],
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
        'snapshot_data' => [
            'document' => [
                'title' => 'Programación publicada',
                'status' => 'published',
                'created_by' => $ownerId,
                'owner_id' => $ownerId,
                'study_type_id' => $studyTypeId,
                'delivery_deadline' => now()->addWeek()->toDateString(),
            ],
        ],
        'changelog' => 'v1',
        'created_by' => $ownerId,
        'published_at' => now(),
        'published_by' => $ownerId,
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

it('allows clone on foreign published with document.clone only', function () {
    grantDocumentVersionCloneHistoryPermissions('document.clone');

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

it('allows titular to clone unpublished draft', function () {
    $templateId = (string) Str::uuid();
    $documentId = (string) Str::uuid();
    $templateBlockId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla',
        'description' => null,
        'visibility_level' => TemplateVisibilityLevel::Personal->value,
        'delivery_deadline' => now()->addWeek(),
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => test()->userId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    TemplateBlock::query()->forceCreate([
        'id' => $templateBlockId,
        'template_id' => $templateId,
        'title' => 'Bloque plantilla',
        'block_state' => 'editable',
        'sort_order' => 0,
    ]);

    Document::query()->forceCreate([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'title' => 'Borrador sin publicar',
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'delivery_deadline' => now()->addWeek()->toDateString(),
        'created_by' => test()->userId,
        'owner_id' => test()->userId,
        'status' => 'draft',
    ]);

    DocumentBlock::query()->forceCreate([
        'id' => (string) Str::uuid(),
        'document_id' => $documentId,
        'template_block_id' => $templateBlockId,
        'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Contenido borrador']]],
        ],
        'is_filled' => true,
        'sort_order' => 0,
        'last_edited_by' => test()->userId,
    ]);

    $this->postJson("/api/v1/documents/{$documentId}/clone")
        ->assertCreated()
        ->assertJsonPath('data.title', 'Borrador sin publicar (copia)');
});

it('clones working draft blocks instead of published snapshot', function () {
    $templateId = (string) Str::uuid();
    $documentId = (string) Str::uuid();
    $headId = (string) Str::uuid();
    $templateBlockId = (string) Str::uuid();
    $publishedBlockId = (string) Str::uuid();
    $draftBlockId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla',
        'description' => null,
        'visibility_level' => TemplateVisibilityLevel::Personal->value,
        'delivery_deadline' => now()->addWeek(),
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => test()->userId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    TemplateBlock::query()->forceCreate([
        'id' => $templateBlockId,
        'template_id' => $templateId,
        'title' => 'Bloque plantilla',
        'block_state' => 'editable',
        'sort_order' => 0,
    ]);

    EntityVersion::query()->forceCreate([
        'id' => $headId,
        'versionable_type' => Document::class,
        'versionable_id' => $documentId,
        'version_number' => 0,
        'status' => 'draft',
        'snapshot_data' => [
            'document' => [
                'title' => 'Programación en nueva versión',
                'status' => 'draft',
                'created_by' => test()->userId,
                'owner_id' => test()->userId,
                'delivery_deadline' => now()->addWeek()->toDateString(),
            ],
        ],
        'changelog' => 'head',
        'created_by' => test()->userId,
    ]);

    EntityVersion::query()->forceCreate([
        'id' => (string) Str::uuid(),
        'versionable_type' => Document::class,
        'versionable_id' => $documentId,
        'version_number' => 1,
        'status' => 'published',
        'snapshot_data' => [
            'document' => [
                'title' => 'Programación en nueva versión',
                'status' => 'published',
                'created_by' => test()->userId,
                'owner_id' => test()->userId,
                'delivery_deadline' => now()->addWeek()->toDateString(),
            ],
            'blocks' => [[
                'id' => $publishedBlockId,
                'template_block_id' => $templateBlockId,
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Texto publicado']]],
                ],
                'is_filled' => true,
                'sort_order' => 0,
            ]],
        ],
        'changelog' => 'v1',
        'created_by' => test()->userId,
        'published_at' => now(),
        'published_by' => test()->userId,
    ]);

    Document::query()->forceCreate([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'title' => 'Programación en nueva versión',
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'delivery_deadline' => now()->addWeek()->toDateString(),
        'created_by' => test()->userId,
        'owner_id' => test()->userId,
        'status' => 'draft',
        'head_entity_version_id' => $headId,
    ]);

    DocumentBlock::query()->forceCreate([
        'id' => $draftBlockId,
        'document_id' => $documentId,
        'template_block_id' => $templateBlockId,
        'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Texto borrador']]],
        ],
        'is_filled' => true,
        'sort_order' => 0,
        'last_edited_by' => test()->userId,
    ]);

    $response = $this->postJson("/api/v1/documents/{$documentId}/clone")
        ->assertCreated();

    $clonedId = $response->json('data.id');
    $clonedBlock = DocumentBlock::query()->where('document_id', $clonedId)->first();

    expect($clonedBlock?->content)->toBe([
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Texto borrador']]],
    ]);
});
