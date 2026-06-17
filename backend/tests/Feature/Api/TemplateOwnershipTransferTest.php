<?php

declare(strict_types=1);

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
    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', [
            'id' => $userId,
            'sub' => $userId,
            'study_type_ids' => ['3'],
            'study_ids' => [],
            'module_ids' => [],
        ]);
    });

    DB::table('user_resolved_permissions')->insert([
        ['user_id' => $this->userId, 'permission_slug' => 'dms.login'],
        ['user_id' => $this->userId, 'permission_slug' => 'template.show'],
        ['user_id' => $this->userId, 'permission_slug' => 'template.update'],
    ]);

    DB::table('processes')->insertOrIgnore([
        'id' => '00000000-0000-0000-0000-000000000001',
        'code' => 'DEFAULT_PROCESS',
        'name' => 'Proceso por defecto',
        'alias' => 'default',
    ]);
});

function makeTransferableTemplate(array $overrides = []): string
{
    $id = (string) Str::uuid();
    $headId = (string) Str::uuid();
    $now = now();

    $attributes = array_merge([
        'visibility_level' => 'study_type',
        'delivery_deadline' => $now->copy()->addWeek(),
        'study_type_id' => '2',
        'study_id' => '101',
        'module_id' => '101_9',
        'team_id' => null,
        'status' => 'draft',
        'review_stages' => 0,
        'review_mode' => 'sequential',
    ], $overrides);

    EntityVersion::query()->forceCreate([
        'id' => $headId,
        'versionable_type' => Template::class,
        'versionable_id' => $id,
        'version_number' => 0,
        'status' => 'draft',
        'snapshot_data' => [
            'template' => array_merge([
                'id' => $id,
                'name' => 'Plantilla transferible',
                'description' => null,
                'process_id' => '00000000-0000-0000-0000-000000000001',
                'created_by' => test()->userId,
            ], $attributes),
        ],
        'created_by' => test()->userId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    Template::query()->forceCreate([
        'id' => $id,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'head_entity_version_id' => $headId,
        'name' => 'Plantilla transferible',
        'description' => null,
        'visibility_level' => $attributes['visibility_level'],
        'delivery_deadline' => $attributes['delivery_deadline'],
        'study_type_id' => $attributes['study_type_id'],
        'study_id' => $attributes['study_id'],
        'module_id' => $attributes['module_id'],
        'team_id' => $attributes['team_id'],
        'created_by' => test()->userId,
        'status' => $attributes['status'],
        'review_stages' => $attributes['review_stages'],
        'review_mode' => $attributes['review_mode'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $id;
}

it('clears academic scope when ownership is transferred', function () {
    $newOwner = (string) Str::uuid();
    $templateId = makeTransferableTemplate();

    $this->patchJson("/api/v1/templates/{$templateId}", [
        'created_by' => $newOwner,
    ])->assertOk();

    $template = Template::query()->withoutGlobalScopes()->findOrFail($templateId);

    expect($template->created_by)->toBe($newOwner)
        ->and($template->study_type_id)->toBeNull()
        ->and($template->study_id)->toBeNull()
        ->and($template->module_id)->toBeNull()
        ->and($template->team_id)->toBeNull();
});

it('allows saving personal template without validating previous owner study type', function () {
    $templateId = makeTransferableTemplate([
        'visibility_level' => 'personal',
        'study_type_id' => '2',
        'study_id' => '101',
        'module_id' => '101_9',
    ]);

    $this->patchJson("/api/v1/templates/{$templateId}", [
        'name' => 'Plantilla personal cedida',
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
    ])->assertOk();

    $template = Template::query()->withoutGlobalScopes()->findOrFail($templateId);

    expect($template->study_type_id)->toBeNull()
        ->and($template->study_id)->toBeNull()
        ->and($template->module_id)->toBeNull();
});
