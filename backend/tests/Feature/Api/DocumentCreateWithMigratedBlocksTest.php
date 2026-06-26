<?php

declare(strict_types=1);

use App\Enums\BlockState;
use App\Enums\TemplateVisibilityLevel;
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

    foreach (['dms.login', 'document.index', 'document.show', 'document.create'] as $slug) {
        DB::table('user_resolved_permissions')->insert([
            'user_id' => $this->userId,
            'permission_slug' => $slug,
        ]);
    }
});

/**
 * @return array{template_version_id: string, block_editable: string, block_locked: string}
 */
function seedPublishedTemplateWithEditableAndLocked(string $ownerId): array
{
    $templateId = (string) Str::uuid();
    $editable = (string) Str::uuid();
    $locked = (string) Str::uuid();

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

    foreach ([$editable, $locked] as $i => $bid) {
        DB::table('template_blocks')->insert([
            'id' => $bid,
            'template_id' => $templateId,
            'title' => 'Bloque',
            'default_content' => json_encode([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'lock-default']]]]),
            'block_state' => $i === 0 ? BlockState::Editable->value : BlockState::Locked->value,
            'sort_order' => $i,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $v = test()->seedCanonicalPublicationForTemplate($templateId, 1, $ownerId, [
        [
            'id' => $editable,
            'type' => 'text',
            'title' => 'Editable',
            'default_content' => [],
            'block_state' => BlockState::Editable->value,
            'mandatory' => false,
            'sort_order' => 0,
        ],
        [
            'id' => $locked,
            'type' => 'text',
            'title' => 'Bloqueado',
            'default_content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'lock-default']]]],
            'block_state' => BlockState::Locked->value,
            'mandatory' => false,
            'sort_order' => 1,
        ],
    ]);

    return ['template_version_id' => $v['entity_version_id'], 'block_editable' => $editable, 'block_locked' => $locked];
}

it('applies migrated content to editable blocks and ignores locked blocks', function () {
    $s = seedPublishedTemplateWithEditableAndLocked(test()->userId);

    $migrated = [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'mi contenido migrado']]]];
    $lockedAttempt = [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'intento sobre locked']]]];

    $res = $this->postJson('/api/v1/documents', [
        'template_version_id' => $s['template_version_id'],
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'title' => 'Nueva programación',
        'migrated_blocks' => [
            $s['block_editable'] => $migrated,
            $s['block_locked'] => $lockedAttempt,
        ],
    ])->assertCreated();

    $documentId = $res->json('data.id') ?? $res->json('data.document.id');
    expect($documentId)->not->toBeNull();

    $editableRow = DB::table('document_blocks')
        ->where('document_id', $documentId)
        ->where('template_block_id', $s['block_editable'])
        ->first();
    $lockedRow = DB::table('document_blocks')
        ->where('document_id', $documentId)
        ->where('template_block_id', $s['block_locked'])
        ->first();

    expect(json_decode((string) $editableRow->content, true))->toBe($migrated);
    // El bloque locked ignora el override y conserva el default de plantilla.
    expect(json_decode((string) $lockedRow->content, true))
        ->toBe([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'lock-default']]]]);
});

it('creates editable block empty when no migrated content is provided', function () {
    $s = seedPublishedTemplateWithEditableAndLocked(test()->userId);

    $res = $this->postJson('/api/v1/documents', [
        'template_version_id' => $s['template_version_id'],
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'title' => 'Nueva programación',
    ])->assertCreated();

    $documentId = $res->json('data.id') ?? $res->json('data.document.id');

    $editableRow = DB::table('document_blocks')
        ->where('document_id', $documentId)
        ->where('template_block_id', $s['block_editable'])
        ->first();

    expect($editableRow->content)->toBeNull();
});
