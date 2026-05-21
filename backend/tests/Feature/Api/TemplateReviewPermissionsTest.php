<?php

declare(strict_types=1);

use App\Enums\TemplateVisibilityLevel;
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
    $this->reviewerId = (string) Str::uuid();

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

    DB::table('user_resolved_permissions')->insert([
        'user_id' => $this->reviewerId,
        'permission_slug' => 'template.review',
    ]);
});

function grantTemplateReviewPermission(string $slug): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id' => test()->userId,
        'permission_slug' => $slug,
    ]);
}

function insertDraftTemplate(string $creatorId, string $visibility, string $status = 'in_review'): string
{
    $templateId = (string) Str::uuid();

    DB::table('templates')->insert([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla revisión',
        'description' => null,
        'visibility_level' => $visibility,
        'delivery_deadline' => now()->addWeek(),
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => $creatorId,
        'status' => $status,
        'review_stages' => 1,
        'review_mode' => 'parallel',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $templateId;
}

it('denies approve review without template.review even when assigned', function () {
    $templateId = insertDraftTemplate(test()->userId, TemplateVisibilityLevel::Global->value);

    DB::table('template_reviewers')->insert([
        'id' => (string) Str::uuid(),
        'template_id' => $templateId,
        'user_id' => test()->userId,
        'stage' => 1,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->postJson("/api/v1/templates/{$templateId}/approve-review")
        ->assertForbidden();
});

it('allows approve review when assigned and has template.review', function () {
    grantTemplateReviewPermission('template.review');

    $templateId = insertDraftTemplate(test()->userId, TemplateVisibilityLevel::Global->value);

    DB::table('template_reviewers')->insert([
        'id' => (string) Str::uuid(),
        'template_id' => $templateId,
        'user_id' => test()->userId,
        'stage' => 1,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->postJson("/api/v1/templates/{$templateId}/approve-review")
        ->assertOk();
});

it('denies sync template reviewers on global template without template.assign-review', function () {
    $templateId = insertDraftTemplate(test()->userId, TemplateVisibilityLevel::Global->value, 'draft');

    $this->postJson("/api/v1/templates/{$templateId}/reviewers", [
        'user_ids' => [test()->reviewerId],
    ])->assertForbidden();
});

it('allows sync template reviewers on global template with template.assign-review', function () {
    grantTemplateReviewPermission('template.assign-review');

    $templateId = insertDraftTemplate(test()->userId, TemplateVisibilityLevel::Global->value, 'draft');

    $this->postJson("/api/v1/templates/{$templateId}/reviewers", [
        'user_ids' => [test()->reviewerId],
    ])->assertOk();
});

it('allows creator to sync reviewers on personal draft without template.assign-review', function () {
    $templateId = insertDraftTemplate(test()->userId, TemplateVisibilityLevel::Personal->value, 'draft');

    $this->postJson("/api/v1/templates/{$templateId}/reviewers", [
        'user_ids' => [test()->reviewerId],
    ])->assertOk();
});
