<?php

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
    Route::get('/health',       [\App\Http\Controllers\Api\HealthCheckController::class, 'index']);
    Route::get('/health/live',  [\App\Http\Controllers\Api\HealthCheckController::class, 'live']);
    Route::get('/health/ready', [\App\Http\Controllers\Api\HealthCheckController::class, 'ready']);
    
    // ── Rutas protegidas por JWT ───────────────────────────────
    Route::middleware('jwt')->group(function () {

        // Sprint 1 — Auth / sesión
        Route::get('/me', [\App\Http\Controllers\Api\AuthController::class, 'me']);

        // Sprint 1 — Grupos
        Route::apiResource('groups', \App\Http\Controllers\Api\GroupController::class);
        Route::post('groups/{group}/members', [\App\Http\Controllers\Api\GroupController::class, 'addMember']);
        Route::delete('groups/{group}/members/{userId}', [\App\Http\Controllers\Api\GroupController::class, 'removeMember']);

        // Sprint 2 — Plantillas
        Route::apiResource('templates', \App\Http\Controllers\Api\TemplateController::class);
        Route::apiResource('templates.blocks', \App\Http\Controllers\Api\TemplateBlockController::class)
            ->shallow();

        // Sprint 3 — Documentos
        Route::apiResource('documents', \App\Http\Controllers\Api\DocumentController::class);
        Route::post('documents/{document}/submit', [\App\Http\Controllers\Api\DocumentController::class, 'submit']);
        Route::post('documents/{document}/publish', [\App\Http\Controllers\Api\DocumentController::class, 'publish']);
        Route::post('documents/{document}/reject', [\App\Http\Controllers\Api\DocumentController::class, 'reject']);
        Route::post('documents/{document}/delegate', [\App\Http\Controllers\Api\DocumentController::class, 'delegate']);

        Route::get('documents/{document}/blocks', [\App\Http\Controllers\Api\DocumentBlockController::class, 'index']);
        Route::put('documents/{document}/blocks/{block}', [\App\Http\Controllers\Api\DocumentBlockController::class, 'update']);

        Route::get('documents/{document}/versions', [\App\Http\Controllers\Api\DocumentVersionController::class, 'index']);

        // Auditoría
        Route::get('documents/{document}/audit', [\App\Http\Controllers\Api\AuditLogController::class, 'indexForDocument']);
        Route::get('templates/{template}/audit', [\App\Http\Controllers\Api\AuditLogController::class, 'indexForTemplate']);
        Route::get('comments/{comment}/audit', [\App\Http\Controllers\Api\AuditLogController::class, 'indexForComment']);

        // Sprint 3 — Compartición
        Route::post('documents/{document}/shares', [\App\Http\Controllers\Api\DocumentShareController::class, 'store']);
        Route::delete('documents/{document}/shares/{userId}', [\App\Http\Controllers\Api\DocumentShareController::class, 'destroy']);

        // Sprint 4 — Revisión
        Route::get('documents/{document}/reviews', [\App\Http\Controllers\Api\ReviewController::class, 'index']);
        Route::post('documents/{document}/reviews/{review}/approve', [\App\Http\Controllers\Api\ReviewController::class, 'approve']);
        Route::post('documents/{document}/reviews/{review}/reject', [\App\Http\Controllers\Api\ReviewController::class, 'reject']);

        // Sprint 5 — Comentarios
        Route::apiResource('documents.comments', \App\Http\Controllers\Api\CommentController::class)
            ->shallow();
        Route::patch('comments/{comment}/resolve', [\App\Http\Controllers\Api\CommentController::class, 'resolve']);

        // Sprint 6 — Dashboard BFF
        Route::get('/dashboard', [\App\Http\Controllers\Api\DashboardController::class, 'index']);

    });

});
