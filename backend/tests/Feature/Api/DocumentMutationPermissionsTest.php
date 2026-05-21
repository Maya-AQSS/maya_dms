<?php

declare(strict_types=1);

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

it('denies document store without document.create', function () {
    grantDocumentMutationPermission('template.show');

    $this->postJson('/api/v1/documents', [
        'title' => 'Nueva programación',
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'delivery_deadline' => now()->addWeek()->toDateString(),
        'template_version_id' => (string) Str::uuid(),
    ])->assertForbidden();
});

it('denies patch document without document.update for non owner', function () {
    $otherOwner = (string) Str::uuid();
    $documentId = (string) Str::uuid();
    $templateId = (string) Str::uuid();

    DB::table('processes')->insertOrIgnore([
        'id' => '00000000-0000-0000-0000-000000000001',
        'code' => 'DEFAULT_PROCESS',
        'name' => 'Proceso por defecto',
        'alias' => 'default',
    ]);

    DB::table('templates')->insert([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla',
        'visibility_level' => 'personal',
        'created_by' => $otherOwner,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'parallel',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('documents')->insert([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'title' => 'Ajeno',
        'created_by' => $otherOwner,
        'owner_id' => $otherOwner,
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->patchJson("/api/v1/documents/{$documentId}", ['title' => 'Hack'])
        ->assertForbidden();
});

it('allows owner to patch own document without document.update', function () {
    $documentId = (string) Str::uuid();
    $templateId = (string) Str::uuid();

    DB::table('processes')->insertOrIgnore([
        'id' => '00000000-0000-0000-0000-000000000001',
        'code' => 'DEFAULT_PROCESS',
        'name' => 'Proceso por defecto',
        'alias' => 'default',
    ]);

    DB::table('templates')->insert([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla',
        'visibility_level' => 'personal',
        'created_by' => test()->userId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'parallel',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('documents')->insert([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'title' => 'Mío',
        'created_by' => test()->userId,
        'owner_id' => test()->userId,
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->patchJson("/api/v1/documents/{$documentId}", ['title' => 'Actualizado'])
        ->assertOk();
});

it('denies delete by non owner without document.delete', function () {
    $otherOwner = (string) Str::uuid();
    $documentId = (string) Str::uuid();
    $templateId = (string) Str::uuid();

    DB::table('templates')->insert([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla',
        'visibility_level' => 'personal',
        'created_by' => $otherOwner,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'parallel',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('documents')->insert([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'title' => 'Ajeno',
        'created_by' => $otherOwner,
        'owner_id' => $otherOwner,
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->deleteJson("/api/v1/documents/{$documentId}")
        ->assertForbidden();
});
