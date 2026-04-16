<?php

use App\Http\Controllers\Api\AcademicHierarchyController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentBlockController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentShareController;
use App\Http\Controllers\Api\DocumentVersionController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\TemplateBlockController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Maya DMS
|--------------------------------------------------------------------------
| Todas las rutas bajo /api/v1 están protegidas por JwtMiddleware (RS256).
| Los controladores son deliberadamente delgados: delegan a Services.
|
| Sprints planificados:
|   Sprint 0 — infraestructura (este archivo, health check)
|   Sprint 1 — auth, groups, academic hierarchy
|   Sprint 2 — templates CRUD
|   Sprint 3 — documents CRUD + block editor
|   Sprint 4 — review workflow
|   Sprint 5 — comments + collaboration
|   Sprint 6 — dashboard BFF
*/

Route::prefix('v1')->group(function () {

    // ── Health check (sin auth) ────────────────────────────────
    Route::get('/health', [HealthCheckController::class, 'index']);
    Route::get('/health/live', [HealthCheckController::class, 'live']);
    Route::get('/health/ready', [HealthCheckController::class, 'ready']);

    // ── Rutas protegidas por JWT ───────────────────────────────
    Route::middleware('jwt')->group(function () {

        // Sprint 1 — Auth / sesión
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/hierarchy', [AcademicHierarchyController::class, 'index']);

        // Sprint 1 — Grupos
        Route::apiResource('groups', GroupController::class)
            ->whereUuid('group');
        Route::post('groups/{group}/members', [GroupController::class, 'addMember'])
            ->whereUuid('group');
        Route::delete('groups/{group}/members/{userId}', [GroupController::class, 'removeMember'])
            ->whereUuid('group');

        // Sprint 2 — Plantillas
        // Combinación de validación UUID (develop) y actualización masiva de bloques (feature).
        Route::apiResource('templates', TemplateController::class)
            ->whereUuid('template');
        Route::put('blocks/bulk', [TemplateBlockController::class, 'bulkUpdate']);
        
        Route::apiResource('templates.blocks', TemplateBlockController::class)
            ->shallow()
            ->whereUuid('template')
            ->whereUuid('block');
        Route::post('templates/{template}/clone', [TemplateController::class, 'clone'])
            ->whereUuid('template');
        Route::post('templates/{template}/submit-review', [TemplateController::class, 'submitForReview'])
            ->whereUuid('template');
        Route::post('templates/{template}/reject-review', [TemplateController::class, 'rejectReview'])
            ->whereUuid('template');
        Route::post('templates/{template}/publish', [TemplateController::class, 'publish'])
            ->whereUuid('template');
        Route::post('templates/{template}/reopen-draft', [TemplateController::class, 'reopenDraft'])
            ->whereUuid('template');
        Route::get('templates/{template}/versions', [TemplateController::class, 'versions'])
            ->whereUuid('template');
        Route::get('template-versions/{template_version}', [TemplateController::class, 'showVersion'])
            ->whereUuid('template_version');
        Route::match(['put', 'patch', 'delete'], 'template-versions/{template_version}', fn () => abort(403, 'Los snapshots de plantilla son de solo inserción (append-only).'))
            ->whereUuid('template_version');

        // Sprint 3 — Documentos
        Route::get('documents/creation-options', [DocumentController::class, 'creationOptions']);
        Route::post('documents/create-from-module', [DocumentController::class, 'createFromModule']);
        Route::get('documents', [DocumentController::class, 'index']);
        Route::post('documents', [DocumentController::class, 'store']);
        Route::get('documents/{document}', [DocumentController::class, 'show'])
            ->whereUuid('document');
        Route::match(['put', 'patch'], 'documents/{document}', [DocumentController::class, 'update'])
            ->whereUuid('document');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])
            ->whereUuid('document');
        Route::post('documents/{document}/submit', [DocumentController::class, 'submit'])
            ->whereUuid('document');
        Route::post('documents/{document}/publish', [DocumentController::class, 'publish'])
            ->whereUuid('document');
        Route::post('documents/{document}/reject', [DocumentController::class, 'reject'])
            ->whereUuid('document');
        Route::post('documents/{document}/delegate', [DocumentController::class, 'delegate'])
            ->whereUuid('document');

        Route::get('documents/{document}/blocks', [DocumentBlockController::class, 'index'])
            ->whereUuid('document');
        Route::put('documents/{document}/blocks/{block}', [DocumentBlockController::class, 'update'])
            ->whereUuid('document')
            ->whereUuid('block');

        Route::get('documents/{document}/versions', [DocumentVersionController::class, 'index'])
            ->whereUuid('document');

        // Auditoría
        Route::get('documents/{document}/audit', [AuditLogController::class, 'indexForDocument'])
            ->whereUuid('document');
        Route::get('templates/{template}/audit', [AuditLogController::class, 'indexForTemplate'])
            ->whereUuid('template');
        Route::get('comments/{comment}/audit', [AuditLogController::class, 'indexForComment'])
            ->whereUuid('comment');

        // Sprint 3 — Compartición
        Route::post('documents/{document}/shares', [DocumentShareController::class, 'store'])
            ->whereUuid('document');
        Route::delete('documents/{document}/shares/{userId}', [DocumentShareController::class, 'destroy'])
            ->whereUuid('document');

        // Sprint 4 — Revisión
        Route::get('documents/{document}/reviews', [ReviewController::class, 'index'])
            ->whereUuid('document');
        Route::post('documents/{document}/reviews/{review}/approve', [ReviewController::class, 'approve'])
            ->whereUuid('document')
            ->whereUuid('review');
        Route::post('documents/{document}/reviews/{review}/reject', [ReviewController::class, 'reject'])
            ->whereUuid('document')
            ->whereUuid('review');

        // Sprint 5 — Comentarios
        Route::apiResource('documents.comments', CommentController::class)
            ->shallow()
            ->whereUuid('document')
            ->whereUuid('comment');
        Route::patch('comments/{comment}/resolve', [CommentController::class, 'resolve'])
            ->whereUuid('comment');

        // Usuarios — búsqueda para asignación de validadores y compartición
        Route::get('/users', [UserController::class, 'index']);

        // Sprint 6 — Dashboard BFF
        Route::get('/dashboard', [DashboardController::class, 'index']);

    });

});
