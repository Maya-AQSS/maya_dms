<?php

declare(strict_types=1);

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
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

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    foreach (['dms.login', 'document.index', 'document.show'] as $slug) {
        DB::table('user_resolved_permissions')->insert([
            'user_id' => $this->userId,
            'permission_slug' => $slug,
        ]);
    }
});

function grantDocumentMutationPermission(string $slug): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id' => test()->userId,
        'permission_slug' => $slug,
    ]);
}

/**
 * Crea una plantilla + documento delegando los metadatos legacy al snapshot
 * cabezal (entity_versions). Los modelos interceptan los atributos delegados
 * y materializan la versión cabeza automáticamente.
 */
function seedDocumentForMutation(string $ownerId): string
{
    $templateId = (string) Str::uuid();
    $documentId = (string) Str::uuid();

    DB::table('processes')->insertOrIgnore([
        'id' => '00000000-0000-0000-0000-000000000001',
        'code' => 'DEFAULT_PROCESS',
        'name' => 'Proceso por defecto',
        'alias' => 'default',
    ]);

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla',
        'description' => null,
        'visibility_level' => TemplateVisibilityLevel::Global->value,
        'delivery_deadline' => now()->addWeek(),
        'document_delivery_deadline' => now()->addWeeks(2),
        'created_by' => $ownerId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    Document::query()->forceCreate([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'title' => 'Documento',
        'delivery_deadline' => now()->addWeek()->toDateString(),
        'created_by' => $ownerId,
        'owner_id' => $ownerId,
        'status' => 'draft',
    ]);

    return $documentId;
}

/**
 * Otorga un share de solo lectura: el documento entra en el scope de
 * visibilidad del usuario (puede resolverlo) pero sin permiso de edición,
 * de modo que las mutaciones denegadas devuelven 403 (política) y no 404.
 */
function grantReadShare(string $documentId, string $userId, string $grantedBy): void
{
    DB::table('document_shares')->insert([
        'id' => (string) Str::uuid(),
        'document_id' => $documentId,
        'user_id' => $userId,
        'permission' => 'read',
        'granted_by' => $grantedBy,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('denies document store without document.create', function () {
    grantDocumentMutationPermission('template.show');

    $this->postJson('/api/v1/documents', [
        'title' => 'Nueva programación',
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_version_id' => (string) Str::uuid(),
    ])->assertForbidden();
});

it('denies patch document without document.update for non owner', function () {
    $otherOwner = (string) Str::uuid();
    $documentId = seedDocumentForMutation($otherOwner);
    grantReadShare($documentId, test()->userId, $otherOwner);

    $this->patchJson("/api/v1/documents/{$documentId}", ['title' => 'Hack'])
        ->assertForbidden();
});

it('allows owner to patch own document without document.update', function () {
    $documentId = seedDocumentForMutation(test()->userId);

    $this->patchJson("/api/v1/documents/{$documentId}", ['title' => 'Actualizado'])
        ->assertOk();
});

it('denies delete by non owner without document.delete', function () {
    $otherOwner = (string) Str::uuid();
    $documentId = seedDocumentForMutation($otherOwner);
    grantReadShare($documentId, test()->userId, $otherOwner);

    $this->deleteJson("/api/v1/documents/{$documentId}")
        ->assertForbidden();
});
