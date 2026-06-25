<?php

declare(strict_types=1);

use App\Enums\BlockState;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maya\Auth\Middleware\JwtMiddleware;
use Tests\Concerns\SeedsTemplatePublicationAnchor;

uses(RefreshDatabase::class, SeedsTemplatePublicationAnchor::class);

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

it('returns template version status when a newer publication exists', function () {
    $templateId = (string) Str::uuid();
    $blockId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla',
        'description' => null,
        'visibility_level' => TemplateVisibilityLevel::Global->value,
        'delivery_deadline' => now()->addWeek(),
        'created_by' => test()->userId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    DB::table('template_blocks')->insert([
        'id' => $blockId,
        'template_id' => $templateId,
        'title' => 'Bloque',
        'default_content' => json_encode([]),
        'block_state' => BlockState::Editable->value,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $v1 = test()->seedCanonicalPublicationForTemplate($templateId, 1, test()->userId, [[
        'id' => $blockId,
        'type' => 'text',
        'title' => 'Bloque',
        'default_content' => [],
        'block_state' => BlockState::Editable->value,
        'sort_order' => 0,
    ]]);

    test()->seedCanonicalPublicationForTemplate($templateId, 2, test()->userId, [[
        'id' => $blockId,
        'type' => 'text',
        'title' => 'Bloque v2',
        'default_content' => [],
        'block_state' => BlockState::Editable->value,
        'sort_order' => 0,
    ]]);

    $documentId = (string) Str::uuid();
    Document::query()->forceCreate([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'template_version_id' => $v1['entity_version_id'],
        'title' => 'Programación',
        'delivery_deadline' => now()->addWeek()->toDateString(),
        'created_by' => test()->userId,
        'owner_id' => test()->userId,
        'status' => 'published',
    ]);

    $this->getJson("/api/v1/documents/{$documentId}/template-version-status")
        ->assertOk()
        ->assertJsonPath('data.current_version.version_number', 1)
        ->assertJsonPath('data.latest_version.version_number', 2)
        ->assertJsonPath('data.has_update', true)
        ->assertJsonPath('data.changelog', 'v2');
});
