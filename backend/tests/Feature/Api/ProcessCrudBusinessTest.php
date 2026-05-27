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

    // Grant permissions needed for all tests
    DB::table('user_resolved_permissions')->insert([
        ['user_id' => $userId, 'permission_slug' => 'dms.login'],
        ['user_id' => $userId, 'permission_slug' => 'process.index'],
        ['user_id' => $userId, 'permission_slug' => 'process.show'],
        ['user_id' => $userId, 'permission_slug' => 'process.create'],
        ['user_id' => $userId, 'permission_slug' => 'process.update'],
        ['user_id' => $userId, 'permission_slug' => 'process.delete'],
    ]);
});

function insertProcess(string $code = 'PX01', ?string $parentId = null): string
{
    $id = (string) Str::uuid();
    DB::table('processes')->insert([
        'id'                => $id,
        'code'              => $code,
        'name'              => "Proceso {$code}",
        'alias'             => strtolower(str_replace('.', '_', $code)),
        'description'       => null,
        'process_parent_id' => $parentId,
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    return $id;
}

// ─── store (POST) ─────────────────────────────────────────────────────────────

it('store returns 201 with the created process', function () {
    $this->postJson('/api/v1/processes', [
        'code'  => 'PBIZ01',
        'name'  => 'Proceso Negocio',
        'alias' => 'neg',
    ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'PBIZ01')
        ->assertJsonPath('data.name', 'Proceso Negocio')
        ->assertJsonPath('data.process_parent_id', null);
});

it('store returns 422 when code is missing', function () {
    $this->postJson('/api/v1/processes', [
        'name'  => 'Sin código',
        'alias' => 'sc',
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['code']);
});

it('store returns 422 when name is missing', function () {
    $this->postJson('/api/v1/processes', [
        'code'  => 'PX_NO_NAME',
        'alias' => 'nx',
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['name']);
});

it('store returns 422 when alias is missing', function () {
    $this->postJson('/api/v1/processes', [
        'code' => 'PX_NO_ALIAS',
        'name' => 'Sin alias',
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['alias']);
});

it('store returns 422 when code is not unique', function () {
    insertProcess('PDUP01');

    $this->postJson('/api/v1/processes', [
        'code'  => 'PDUP01',
        'name'  => 'Duplicado',
        'alias' => 'dup',
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['code']);
});

it('store accepts a valid process_parent_id', function () {
    $parentId = insertProcess('PPAR01');

    $this->postJson('/api/v1/processes', [
        'code'              => 'PCHILD01',
        'name'              => 'Hijo',
        'alias'             => 'hijo',
        'process_parent_id' => $parentId,
    ])
        ->assertCreated()
        ->assertJsonPath('data.process_parent_id', $parentId);
});

// ─── show (GET /:id) ──────────────────────────────────────────────────────────

it('show returns 404 for non-existent process', function () {
    $this->getJson('/api/v1/processes/'.Str::uuid())->assertNotFound();
});

it('show returns the process shape', function () {
    $id = insertProcess('PSHOW01');

    $this->getJson("/api/v1/processes/{$id}")
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.code', 'PSHOW01')
        ->assertJsonStructure(['data' => ['id', 'code', 'name', 'alias', 'description', 'process_parent_id', 'color', 'icon']]);
});

// ─── update (PATCH /:id) ──────────────────────────────────────────────────────

it('update returns 200 with updated data', function () {
    $id = insertProcess('PUPD01');

    $this->patchJson("/api/v1/processes/{$id}", [
        'code'  => 'PUPD01',
        'name'  => 'Nombre actualizado',
        'alias' => 'upd',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nombre actualizado');
});

it('update returns 422 when required fields are missing', function () {
    $id = insertProcess('PUPD02');

    $this->patchJson("/api/v1/processes/{$id}", [])->assertUnprocessable()
         ->assertJsonValidationErrors(['code', 'name', 'alias']);
});

it('update returns 404 for non-existent process', function () {
    $this->patchJson('/api/v1/processes/'.Str::uuid(), [
        'code'  => 'PNONE',
        'name'  => 'No existe',
        'alias' => 'none',
    ])->assertNotFound();
});

it('update returns 422 when process_parent_id is itself', function () {
    $id = insertProcess('PSELF01');

    $this->patchJson("/api/v1/processes/{$id}", [
        'code'              => 'PSELF01',
        'name'              => 'Autorreferencia',
        'alias'             => 'self',
        'process_parent_id' => $id,
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['process_parent_id']);
});

// ─── destroy (DELETE /:id) ────────────────────────────────────────────────────

it('destroy returns 404 for non-existent process', function () {
    $this->deleteJson('/api/v1/processes/'.Str::uuid())->assertNotFound();
});

it('destroy returns 204 for a process with no dependents', function () {
    $id = insertProcess('PDEL01');

    $this->deleteJson("/api/v1/processes/{$id}")->assertNoContent();

    $this->assertDatabaseMissing('processes', ['id' => $id]);
});

it('destroy returns 409 when process has child processes', function () {
    $parentId = insertProcess('PDEL_PARENT');
    insertProcess('PDEL_CHILD', $parentId);

    $this->deleteJson("/api/v1/processes/{$parentId}")
        ->assertStatus(409);
});

it('409 response contains a descriptive error message', function () {
    $parentId = insertProcess('PDEL_MSG');
    insertProcess('PDEL_MSG_CHILD', $parentId);

    $response = $this->deleteJson("/api/v1/processes/{$parentId}")
        ->assertStatus(409);

    $this->assertStringContainsString(
        'subprocesos',
        (string) $response->getContent(),
    );
});
