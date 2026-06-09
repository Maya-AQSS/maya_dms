<?php

declare(strict_types=1);

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
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

    // Las rutas viven bajo el grupo `permission:dms.login`.
    DB::table('user_resolved_permissions')->insert([
        'user_id' => $this->userId,
        'permission_slug' => 'dms.login',
    ]);
});

function grantBlockPermissions(string ...$slugs): void
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
function seedTemplateWithBlock(string $creatorId): array
{
    $templateId = (string) Str::uuid();
    $blockId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla bloques permisos',
        'description' => null,
        'visibility_level' => TemplateVisibilityLevel::Global->value,
        'created_by' => $creatorId,
        'status' => 'draft',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    TemplateBlock::query()->forceCreate([
        'id' => $blockId,
        'template_id' => $templateId,
        'title' => 'Bloque',
        'default_content' => null,
        'block_state' => 'editable',
        'sort_order' => 0,
    ]);

    // Snapshot publicado: una plantilla global con versión publicada es visible
    // en el catálogo para no creadores, de modo que la autorización llegue al
    // gate de permiso (no se resuelva como 404 por invisibilidad).
    DB::table('entity_versions')->insert([
        'id' => (string) Str::uuid(),
        'versionable_type' => Template::class,
        'versionable_id' => $templateId,
        'version_number' => 1,
        'status' => 'published',
        'is_snapshot_immutable' => true,
        'created_by' => $creatorId,
        'published_by' => $creatorId,
        'published_at' => now(),
        'changelog' => 'v1',
        'snapshot_data' => json_encode([
            'template' => ['id' => $templateId, 'visibility_level' => 'global', 'status' => 'published'],
        ], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['templateId' => $templateId, 'blockId' => $blockId];
}

it('denies template blocks index without block.index', function () {
    grantBlockPermissions('template.show', 'template.update');

    $ctx = seedTemplateWithBlock(test()->otherUserId);

    $this->getJson("/api/v1/templates/{$ctx['templateId']}/blocks")
        ->assertForbidden();
});

it('allows template blocks index with block.index and template read access without companion slug', function () {
    // El listado de bloques de plantilla ya no exige compañero de mutación
    // (cambio en BlockPolicy::listForTemplate): basta block.index + acceso de
    // lectura sobre la plantilla (template.show sobre plantilla visible).
    grantBlockPermissions('template.show', 'block.index');

    $ctx = seedTemplateWithBlock(test()->otherUserId);

    $this->getJson("/api/v1/templates/{$ctx['templateId']}/blocks")
        ->assertOk();
});

it('allows template blocks index with block.index and template.update', function () {
    grantBlockPermissions('template.show', 'block.index', 'template.update');

    $ctx = seedTemplateWithBlock(test()->otherUserId);

    $this->getJson("/api/v1/templates/{$ctx['templateId']}/blocks")
        ->assertOk();
});

it('denies block show without block.show', function () {
    grantBlockPermissions('template.show', 'block.index', 'template.update');

    $ctx = seedTemplateWithBlock(test()->otherUserId);

    $this->getJson("/api/v1/blocks/{$ctx['blockId']}")
        ->assertForbidden();
});

it('allows block show with block.show and template.update', function () {
    grantBlockPermissions('template.show', 'block.show', 'template.update');

    $ctx = seedTemplateWithBlock(test()->otherUserId);

    $this->getJson("/api/v1/blocks/{$ctx['blockId']}")
        ->assertOk();
});

it('denies document blocks index without block.index for owner', function () {
    grantBlockPermissions('document.update');

    $ownerId = test()->userId;
    $documentId = (string) Str::uuid();
    $templateId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Tpl',
        'visibility_level' => TemplateVisibilityLevel::Personal->value,
        'created_by' => $ownerId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    Document::query()->forceCreate([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'title' => 'Doc',
        'created_by' => $ownerId,
        'owner_id' => $ownerId,
        'status' => 'draft',
    ]);

    $this->getJson("/api/v1/documents/{$documentId}/blocks")
        ->assertForbidden();
});

it('allows document blocks index for owner with block.index and document.update', function () {
    grantBlockPermissions('block.index', 'document.update');

    $ownerId = test()->userId;
    $documentId = (string) Str::uuid();
    $templateId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Tpl',
        'visibility_level' => TemplateVisibilityLevel::Personal->value,
        'created_by' => $ownerId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    Document::query()->forceCreate([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'title' => 'Doc',
        'created_by' => $ownerId,
        'owner_id' => $ownerId,
        'status' => 'draft',
    ]);

    $this->getJson("/api/v1/documents/{$documentId}/blocks")
        ->assertOk();
});
