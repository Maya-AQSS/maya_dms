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
        Route::apiResource('groups', GroupController::class);
        Route::post('groups/{group}/members', [GroupController::class, 'addMember']);
        Route::delete('groups/{group}/members/{userId}', [GroupController::class, 'removeMember']);

        // Sprint 2 — Plantillas
        Route::apiResource('templates', TemplateController::class);
        Route::apiResource('templates.blocks', TemplateBlockController::class)
            ->shallow();
        Route::post('templates/{template}/clone', [TemplateController::class, 'clone']);
        Route::post('templates/{template}/submit-review', [TemplateController::class, 'submitForReview']);
        Route::post('templates/{template}/reject-review', [TemplateController::class, 'rejectReview']);
        Route::post('templates/{template}/publish', [TemplateController::class, 'publish']);
        Route::post('templates/{template}/reopen-draft', [TemplateController::class, 'reopenDraft']);
        Route::get('templates/{template}/versions', [TemplateController::class, 'versions']);
        Route::get('template-versions/{template_version}', [TemplateController::class, 'showVersion']);
        Route::match(['put', 'patch', 'delete'], 'template-versions/{template_version}', fn () => abort(403, 'Los snapshots de plantilla son de solo inserción (append-only).'));

        // Sprint 3 — Documentos
        Route::apiResource('documents', DocumentController::class);
        Route::post('documents/{document}/submit', [DocumentController::class, 'submit']);
        Route::post('documents/{document}/publish', [DocumentController::class, 'publish']);
        Route::post('documents/{document}/reject', [DocumentController::class, 'reject']);
        Route::post('documents/{document}/delegate', [DocumentController::class, 'delegate']);

        Route::get('documents/{document}/blocks', [DocumentBlockController::class, 'index']);
        Route::put('documents/{document}/blocks/{block}', [DocumentBlockController::class, 'update']);

        Route::get('documents/{document}/versions', [DocumentVersionController::class, 'index']);

        // Auditoría
        Route::get('documents/{document}/audit', [AuditLogController::class, 'indexForDocument']);
        Route::get('templates/{template}/audit', [AuditLogController::class, 'indexForTemplate']);
        Route::get('comments/{comment}/audit', [AuditLogController::class, 'indexForComment']);

        // Sprint 3 — Compartición
        Route::post('documents/{document}/shares', [DocumentShareController::class, 'store']);
        Route::delete('documents/{document}/shares/{userId}', [DocumentShareController::class, 'destroy']);

        // Sprint 4 — Revisión
        Route::get('documents/{document}/reviews', [ReviewController::class, 'index']);
        Route::post('documents/{document}/reviews/{review}/approve', [ReviewController::class, 'approve']);
        Route::post('documents/{document}/reviews/{review}/reject', [ReviewController::class, 'reject']);

        // Sprint 5 — Comentarios
        Route::apiResource('documents.comments', CommentController::class)
            ->shallow();
        Route::patch('comments/{comment}/resolve', [CommentController::class, 'resolve']);

        // Sprint 6 — Dashboard BFF
        Route::get('/dashboard', [DashboardController::class, 'index']);

    });

});
