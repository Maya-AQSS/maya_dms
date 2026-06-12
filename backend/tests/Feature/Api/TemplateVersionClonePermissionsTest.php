<?php

declare(strict_types=1);

use App\Enums\BlockState;
use App\Enums\TemplateVisibilityLevel;
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

    foreach (['dms.login', 'template.index', 'template.show', 'template.create'] as $slug) {
        DB::table('user_resolved_permissions')->insert([
            'user_id' => $this->userId,
            'permission_slug' => $slug,
        ]);
    }
});

function grantVersionCloneHistoryPermissions(string ...$slugs): void
{
    foreach ($slugs as $slug) {
        DB::table('user_resolved_permissions')->insert([
            'user_id' => test()->userId,
            'permission_slug' => $slug,
        ]);
    }
}

function seedPublishedGlobalTemplate(string $creatorId): string
{
    $templateId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla publicada',
        'description' => null,
        'visibility_level' => TemplateVisibilityLevel::Global->value,
        'delivery_deadline' => now()->addWeek(),
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
        'id' => (string) Str::uuid(),
        'versionable_type' => Template::class,
        'versionable_id' => $templateId,
        'version_number' => 1,
        'status' => 'published',
        'snapshot_data' => ['template' => ['name' => 'Plantilla publicada']],
        'changelog' => 'v1',
        'created_by' => $creatorId,
        'published_at' => now(),
        'published_by' => $creatorId,
    ]);

    return $templateId;
}

it('denies new-version on foreign published template without template.version', function () {
    $templateId = seedPublishedGlobalTemplate(test()->otherUserId);

    $this->postJson("/api/v1/templates/{$templateId}/new-version")
        ->assertForbidden();
});

it('allows new-version on foreign published template with template.version', function () {
    grantVersionCloneHistoryPermissions('template.version');

    $templateId = seedPublishedGlobalTemplate(test()->otherUserId);

    $this->postJson("/api/v1/templates/{$templateId}/new-version")
        ->assertOk();
});

it('denies clone on foreign published without template.clone', function () {
    grantVersionCloneHistoryPermissions('template.update');

    $templateId = seedPublishedGlobalTemplate(test()->otherUserId);

    $this->postJson("/api/v1/templates/{$templateId}/clone")
        ->assertForbidden();
});

it('allows clone on foreign published with template.clone only', function () {
    grantVersionCloneHistoryPermissions('template.clone');

    $templateId = seedPublishedGlobalTemplate(test()->otherUserId);

    $this->postJson("/api/v1/templates/{$templateId}/clone")
        ->assertCreated();
});

it('allows creator to clone own published without template.clone slug', function () {
    $templateId = seedPublishedGlobalTemplate(test()->userId);

    $this->postJson("/api/v1/templates/{$templateId}/clone")
        ->assertCreated();
});

it('denies versions list without template.history.view for non-creator', function () {
    $templateId = seedPublishedGlobalTemplate(test()->otherUserId);

    $this->getJson("/api/v1/templates/{$templateId}/versions")
        ->assertNotFound();
});

it('allows versions list for non-creator with template.history.view', function () {
    grantVersionCloneHistoryPermissions('template.history.view');

    $templateId = seedPublishedGlobalTemplate(test()->otherUserId);

    $this->getJson("/api/v1/templates/{$templateId}/versions")
        ->assertOk();
});

it('allows versions list for creator without template.history.view', function () {
    $templateId = seedPublishedGlobalTemplate(test()->userId);

    $this->getJson("/api/v1/templates/{$templateId}/versions")
        ->assertOk();
});

it('allows creator to clone unpublished draft', function () {
    $templateId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Borrador sin publicar',
        'description' => null,
        'visibility_level' => TemplateVisibilityLevel::Personal->value,
        'delivery_deadline' => now()->addWeek(),
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => test()->userId,
        'status' => 'draft',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    TemplateBlock::query()->forceCreate([
        'id' => (string) Str::uuid(),
        'template_id' => $templateId,
        'title' => 'Bloque borrador',
        'block_state' => BlockState::Editable->value,
        'sort_order' => 0,
    ]);

    $this->postJson("/api/v1/templates/{$templateId}/clone")
        ->assertCreated()
        ->assertJsonPath('data.name', 'Borrador sin publicar (copia)');
});

it('clones working draft blocks instead of published snapshot', function () {
    $templateId = (string) Str::uuid();
    $publishedBlockId = (string) Str::uuid();
    $draftBlockId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla en nueva versión',
        'description' => null,
        'visibility_level' => TemplateVisibilityLevel::Personal->value,
        'delivery_deadline' => now()->addWeek(),
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => test()->userId,
        'status' => 'draft',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    EntityVersion::query()->forceCreate([
        'id' => (string) Str::uuid(),
        'versionable_type' => Template::class,
        'versionable_id' => $templateId,
        'version_number' => 1,
        'status' => 'published',
        'snapshot_data' => [
            'template' => ['name' => 'Plantilla en nueva versión'],
            'blocks' => [[
                'id' => $publishedBlockId,
                'title' => 'Título publicado',
                'block_state' => 'editable',
                'sort_order' => 0,
            ]],
        ],
        'changelog' => 'v1',
        'created_by' => test()->userId,
        'published_at' => now(),
        'published_by' => test()->userId,
    ]);

    TemplateBlock::query()->forceCreate([
        'id' => $draftBlockId,
        'template_id' => $templateId,
        'title' => 'Título borrador',
        'block_state' => BlockState::Editable->value,
        'sort_order' => 0,
    ]);

    $response = $this->postJson("/api/v1/templates/{$templateId}/clone")
        ->assertCreated();

    $clonedId = $response->json('data.id');
    $clonedBlock = TemplateBlock::query()->where('template_id', $clonedId)->first();

    expect($clonedBlock?->title)->toBe('Título borrador');
});
