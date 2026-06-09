<?php

declare(strict_types=1);

use App\Enums\TemplateVisibilityLevel;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateReviewer;
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

function grantCommentPermissions(string ...$slugs): void
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
function seedTemplateInReview(string $creatorId, string $reviewerId): array
{
    $templateId = (string) Str::uuid();
    $blockId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla en revisión',
        'visibility_level' => TemplateVisibilityLevel::Global->value,
        'created_by' => $creatorId,
        'status' => 'in_review',
        'review_stages' => 1,
        'review_mode' => 'parallel',
    ]);

    TemplateReviewer::query()->forceCreate([
        'template_id' => $templateId,
        'user_id' => $reviewerId,
        'stage' => 1,
    ]);

    TemplateBlock::query()->forceCreate([
        'id' => $blockId,
        'template_id' => $templateId,
        'title' => 'Bloque',
        'block_state' => 'editable',
        'sort_order' => 0,
    ]);

    return ['templateId' => $templateId, 'blockId' => $blockId];
}

it('denies template comment store without comment-block.create', function () {
    grantCommentPermissions('template.show');

    $ctx = seedTemplateInReview(test()->otherUserId, test()->userId);

    $this->postJson("/api/v1/templates/{$ctx['templateId']}/comments", [
        'body' => 'Observación',
        'blockable_id' => $ctx['blockId'],
    ])->assertForbidden();
});

it('allows template comment store for reviewer with comment-block.create and template.review', function () {
    grantCommentPermissions('template.show', 'comment-block.create', 'template.review');

    $ctx = seedTemplateInReview(test()->otherUserId, test()->userId);

    $this->postJson("/api/v1/templates/{$ctx['templateId']}/comments", [
        'body' => 'Observación del revisor',
        'blockable_id' => $ctx['blockId'],
    ])->assertCreated();
});

it('allows template comment store for creator without template.review', function () {
    grantCommentPermissions('comment-block.create');

    $templateId = (string) Str::uuid();
    $blockId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Mi plantilla',
        'visibility_level' => TemplateVisibilityLevel::Personal->value,
        'created_by' => test()->userId,
        'status' => 'draft',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    TemplateBlock::query()->forceCreate([
        'id' => $blockId,
        'template_id' => $templateId,
        'title' => 'Bloque',
        'block_state' => 'editable',
        'sort_order' => 0,
    ]);

    $this->postJson("/api/v1/templates/{$templateId}/comments", [
        'body' => 'Nota del creador',
        'blockable_id' => $blockId,
    ])->assertCreated();
});

it('denies comment destroy without comment-block.delete', function () {
    grantCommentPermissions('comment-block.create', 'template.show');

    $ctx = seedTemplateInReview(test()->otherUserId, test()->userId);

    // Un autor puede borrar su propio comentario sin el slug; para verificar la
    // denegación, el comentario debe ser de otro usuario.
    $commentId = (string) Str::uuid();
    Comment::query()->forceCreate([
        'id' => $commentId,
        'commentable_type' => Template::class,
        'commentable_id' => $ctx['templateId'],
        'commentable_version' => 1,
        'blockable_type' => TemplateBlock::class,
        'blockable_id' => $ctx['blockId'],
        'author_id' => test()->otherUserId,
        'body' => 'Comentario',
    ]);

    $this->deleteJson("/api/v1/comments/{$commentId}")
        ->assertForbidden();
});

it('allows reviewer to destroy own comment with comment-block.delete', function () {
    grantCommentPermissions('template.show', 'comment-block.create', 'comment-block.delete', 'template.review');

    $ctx = seedTemplateInReview(test()->otherUserId, test()->userId);

    $commentId = (string) Str::uuid();
    Comment::query()->forceCreate([
        'id' => $commentId,
        'commentable_type' => Template::class,
        'commentable_id' => $ctx['templateId'],
        'commentable_version' => 1,
        'blockable_type' => TemplateBlock::class,
        'blockable_id' => $ctx['blockId'],
        'author_id' => test()->userId,
        'body' => 'Comentario',
    ]);

    $this->deleteJson("/api/v1/comments/{$commentId}")
        ->assertNoContent();
});

it('denies document comment store for owner without comment-block.create', function () {
    grantCommentPermissions('document.update');

    $ownerId = test()->userId;
    $templateId = (string) Str::uuid();
    $documentId = (string) Str::uuid();

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

    $this->postJson("/api/v1/documents/{$documentId}/comments", [
        'body' => 'Nota',
    ])->assertForbidden();
});

it('allows document owner comment with comment-block.create', function () {
    grantCommentPermissions('comment-block.create');

    $ownerId = test()->userId;
    $templateId = (string) Str::uuid();
    $documentId = (string) Str::uuid();

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
        'status' => 'in_review',
    ]);

    DocumentReview::query()->forceCreate([
        'id' => (string) Str::uuid(),
        'document_id' => $documentId,
        'reviewer_id' => test()->otherUserId,
        'stage' => 1,
    ]);

    $this->postJson("/api/v1/documents/{$documentId}/comments", [
        'body' => 'Nota del titular',
    ])->assertCreated();
});

it('allows document edit-share collaborator to comment when rejected', function () {
    $ownerId = test()->userId;
    $collabId = test()->otherUserId;

    foreach (['dms.login', 'comment-block.create'] as $slug) {
        DB::table('user_resolved_permissions')->insert([
            'user_id' => $collabId,
            'permission_slug' => $slug,
        ]);
    }

    $templateId = (string) Str::uuid();
    $documentId = (string) Str::uuid();

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
        'status' => 'rejected',
    ]);

    DB::table('document_shares')->insert([
        'id' => (string) Str::uuid(),
        'document_id' => $documentId,
        'user_id' => $collabId,
        'permission' => 'edit',
        'granted_by' => $ownerId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app('events')->listen(RouteMatched::class, function ($event) use ($collabId) {
        $event->request->attributes->set('jwt_user', ['id' => $collabId, 'sub' => $collabId]);
    });

    $this->postJson("/api/v1/documents/{$documentId}/comments", [
        'body' => 'Nota del colaborador',
    ])->assertCreated();
});
