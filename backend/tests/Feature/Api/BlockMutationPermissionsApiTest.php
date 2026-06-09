<?php

declare(strict_types=1);

use App\Enums\BlockState;
use App\Enums\TemplateVisibilityLevel;
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

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    // Las rutas viven bajo el grupo `permission:dms.login`.
    DB::table('user_resolved_permissions')->insert([
        'user_id' => $this->userId,
        'permission_slug' => 'dms.login',
    ]);
});

function grantBlockMutationPermissions(string ...$slugs): void
{
    foreach ($slugs as $slug) {
        DB::table('user_resolved_permissions')->insert([
            'user_id' => test()->userId,
            'permission_slug' => $slug,
        ]);
    }
}

/**
 * @return array{templateId: string, blockId: string}
 */
function seedPersonalTemplateWithBlock(string $creatorId): array
{
    $templateId = (string) Str::uuid();
    $blockId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla',
        'visibility_level' => TemplateVisibilityLevel::Personal->value,
        'created_by' => $creatorId,
        'status' => 'draft',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    TemplateBlock::query()->forceCreate([
        'id' => $blockId,
        'template_id' => $templateId,
        'title' => 'Bloque',
        'block_state' => BlockState::Editable->value,
        'sort_order' => 0,
    ]);

    return ['templateId' => $templateId, 'blockId' => $blockId];
}

it('denies template block store without block.create', function () {
    grantBlockMutationPermissions('template.show', 'template.update');

    $ctx = seedPersonalTemplateWithBlock(test()->userId);

    $this->postJson("/api/v1/templates/{$ctx['templateId']}/blocks", [
        'title' => 'Nuevo',
        'block_state' => 'editable',
    ])->assertForbidden();
});

it('allows template block store for owner with block.create only on personal draft', function () {
    grantBlockMutationPermissions('block.create');

    $ctx = seedPersonalTemplateWithBlock(test()->userId);

    $this->postJson("/api/v1/templates/{$ctx['templateId']}/blocks", [
        'title' => 'Nuevo',
        'block_state' => 'editable',
    ])->assertCreated();
});

it('allows template block store for owner with block.create and template.update', function () {
    grantBlockMutationPermissions('block.create', 'template.update');

    $ctx = seedPersonalTemplateWithBlock(test()->userId);

    $this->postJson("/api/v1/templates/{$ctx['templateId']}/blocks", [
        'title' => 'Nuevo',
        'block_state' => 'editable',
    ])->assertCreated();
});

it('denies template block update without block.update', function () {
    grantBlockMutationPermissions('template.show', 'template.update');

    $ctx = seedPersonalTemplateWithBlock(test()->userId);

    $this->putJson("/api/v1/blocks/{$ctx['blockId']}", [
        'title' => 'Renombrado',
    ])->assertForbidden();
});

it('allows template block update with block.update and template.update', function () {
    grantBlockMutationPermissions('block.update', 'template.update');

    $ctx = seedPersonalTemplateWithBlock(test()->userId);

    $this->putJson("/api/v1/blocks/{$ctx['blockId']}", [
        'title' => 'Renombrado',
    ])->assertOk()
        ->assertJsonPath('data.title', 'Renombrado');
});

it('denies template block delete without block.delete', function () {
    grantBlockMutationPermissions('block.update', 'template.update');

    $ctx = seedPersonalTemplateWithBlock(test()->userId);

    TemplateBlock::query()->forceCreate([
        'id' => (string) Str::uuid(),
        'template_id' => $ctx['templateId'],
        'title' => 'Otro',
        'block_state' => BlockState::Editable->value,
        'sort_order' => 1,
    ]);

    $this->deleteJson("/api/v1/blocks/{$ctx['blockId']}")
        ->assertForbidden();
});

it('allows template block delete for owner with block.delete and template.update', function () {
    grantBlockMutationPermissions('block.delete', 'template.update');

    $ctx = seedPersonalTemplateWithBlock(test()->userId);

    TemplateBlock::query()->forceCreate([
        'id' => (string) Str::uuid(),
        'template_id' => $ctx['templateId'],
        'title' => 'Otro',
        'block_state' => BlockState::Editable->value,
        'sort_order' => 1,
    ]);

    $this->deleteJson("/api/v1/blocks/{$ctx['blockId']}")
        ->assertNoContent();
});
