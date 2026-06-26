<?php

declare(strict_types=1);

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

    DB::table('user_resolved_permissions')->insert([
        ['user_id' => $this->userId, 'permission_slug' => 'dms.login'],
        ['user_id' => $this->userId, 'permission_slug' => 'document.index'],
    ]);

    DB::table('processes')->insertOrIgnore([
        'id' => '00000000-0000-0000-0000-000000000001',
        'code' => 'DEFAULT_PROCESS',
        'name' => 'Proceso por defecto',
        'alias' => 'default',
    ]);

    $this->templateId = (string) Str::uuid();
    Template::query()->forceCreate([
        'id' => $this->templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla ancla',
        'description' => null,
        'visibility_level' => 'personal',
        'delivery_deadline' => null,
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => $this->userId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'sequential',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

function makeOwnDocumentForAcademicFilter(string $title, array $overrides = []): string
{
    $id = (string) Str::uuid();
    Document::query()->forceCreate(array_merge([
        'id' => $id,
        'template_id' => test()->templateId,
        'title' => $title,
        'study_id' => null,
        'created_by' => test()->userId,
        'owner_id' => test()->userId,
        'status' => 'draft',
        'delivery_deadline' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $id;
}

it('filters documents by study_type_ids csv as OR within dimension', function () {
    $typeA = (string) Str::uuid();
    $typeB = (string) Str::uuid();
    $matchA = makeOwnDocumentForAcademicFilter('Tipo A', ['study_type_id' => $typeA]);
    $matchB = makeOwnDocumentForAcademicFilter('Tipo B', ['study_type_id' => $typeB]);
    makeOwnDocumentForAcademicFilter('Otro tipo', ['study_type_id' => (string) Str::uuid()]);

    $ids = $this->getJson('/api/v1/documents?study_type_ids='.$typeA.','.$typeB)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toEqualCanonicalizing([$matchA, $matchB]);
});

it('applies profile_academic_default with single module as cascade filter', function () {
    $moduleId = (string) Str::uuid();
    $match = makeOwnDocumentForAcademicFilter('Del módulo', ['module_id' => $moduleId]);
    makeOwnDocumentForAcademicFilter('Otro módulo', ['module_id' => (string) Str::uuid()]);

    $userId = test()->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId, $moduleId) {
        $event->request->attributes->set('jwt_user', [
            'id' => $userId,
            'sub' => $userId,
            'module_ids' => [$moduleId],
        ]);
    });

    $ids = $this->getJson('/api/v1/documents?profile_academic_default=1')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$match]);
});

it('applies profile_academic_default with multiple modules as union OR', function () {
    $moduleA = (string) Str::uuid();
    $moduleB = (string) Str::uuid();
    $matchA = makeOwnDocumentForAcademicFilter('Módulo A', ['module_id' => $moduleA]);
    $matchB = makeOwnDocumentForAcademicFilter('Módulo B', ['module_id' => $moduleB]);
    makeOwnDocumentForAcademicFilter('Otro módulo', ['module_id' => (string) Str::uuid()]);

    $userId = test()->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId, $moduleA, $moduleB) {
        $event->request->attributes->set('jwt_user', [
            'id' => $userId,
            'sub' => $userId,
            'module_ids' => [$moduleA, $moduleB],
        ]);
    });

    $ids = $this->getJson('/api/v1/documents?profile_academic_default=1')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toEqualCanonicalizing([$matchA, $matchB]);
});

it('returns full catalog when profile_academic_default has empty scopes', function () {
    $docA = makeOwnDocumentForAcademicFilter('Uno');
    $docB = makeOwnDocumentForAcademicFilter('Dos');

    $ids = $this->getJson('/api/v1/documents?profile_academic_default=1')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toEqualCanonicalizing([$docA, $docB]);
});

it('applies profile_academic_default union across study type and module', function () {
    $studyTypeId = (string) Str::uuid();
    $moduleId = (string) Str::uuid();
    $byType = makeOwnDocumentForAcademicFilter('Por tipo', ['study_type_id' => $studyTypeId]);
    $byModule = makeOwnDocumentForAcademicFilter('Por módulo', ['module_id' => $moduleId]);
    makeOwnDocumentForAcademicFilter('Fuera', [
        'study_type_id' => (string) Str::uuid(),
        'module_id' => (string) Str::uuid(),
    ]);

    $userId = test()->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId, $studyTypeId, $moduleId) {
        $event->request->attributes->set('jwt_user', [
            'id' => $userId,
            'sub' => $userId,
            'study_type_ids' => [$studyTypeId],
            'module_ids' => [$moduleId],
        ]);
    });

    $ids = $this->getJson('/api/v1/documents?profile_academic_default=1')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toEqualCanonicalizing([$byType, $byModule]);
});
