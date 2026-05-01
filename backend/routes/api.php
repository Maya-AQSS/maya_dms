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
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\ProcessController;
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
*/

Route::prefix('v1')->group(function () {

    // ── Health check (sin auth) ────────────────────────────────
    Route::get('/health', [HealthCheckController::class, 'index']);
    Route::get('/health/live', [HealthCheckController::class, 'live']);
    Route::get('/health/ready', [HealthCheckController::class, 'ready']);

    // ── Rutas protegidas por JWT ───────────────────────────────
    Route::middleware('jwt')->group(function () {

        // Autenticación y sesión
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/hierarchy', [AcademicHierarchyController::class, 'index']);
        Route::get('/processes', [ProcessController::class, 'index']);

        // Plantillas
        // Combinación de validación UUID (develop) y actualización masiva de bloques (feature).
        Route::apiResource('templates', TemplateController::class)
            ->whereUuid('template');
        Route::post('templates/{template}/reviewers', [TemplateController::class, 'syncReviewers'])
            ->whereUuid('template');
        Route::post('templates/{template}/document-reviewers', [TemplateController::class, 'syncDocumentReviewers'])
            ->whereUuid('template');
        Route::put('blocks/bulk', [TemplateBlockController::class, 'bulkUpdate']);
        Route::patch('templates/{template}/blocks/reorder', [TemplateBlockController::class, 'reorder'])
            ->whereUuid('template');

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
        Route::post('templates/{template}/approve-review', [TemplateController::class, 'approveReview'])
            ->whereUuid('template');
        Route::post('templates/{template}/publish', [TemplateController::class, 'publish'])
            ->whereUuid('template');
        Route::get('templates/{template}/versions', [TemplateController::class, 'versions'])
            ->whereUuid('template');
        Route::get('template-versions/{template_version}', [TemplateController::class, 'showVersion'])
            ->whereUuid('template_version');
        Route::match(['put', 'patch', 'delete'], 'template-versions/{template_version}', fn () => abort(403, 'Los snapshots de plantilla son de solo inserción (append-only).'))
            ->whereUuid('template_version');

        // Documentos
        Route::get('documents/creation-options', [DocumentController::class, 'creationOptions']);
        Route::post('documents/create-from-module', [DocumentController::class, 'createFromModule']);
        Route::get('documents', [DocumentController::class, 'index']);
        Route::post('documents', [DocumentController::class, 'store']);
        Route::get('documents/{document}/template-version-status', [DocumentController::class, 'templateVersionStatus'])
            ->whereUuid('document');
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
        Route::post('documents/{document}/delegate', [DocumentController::class, 'delegate'])
            ->whereUuid('document');

        Route::get('documents/{document}/blocks', [DocumentBlockController::class, 'index'])
            ->whereUuid('document');
        Route::put('documents/{document}/blocks/{block}', [DocumentBlockController::class, 'update'])
            ->whereUuid('document')
            ->whereUuid('block');

        Route::get('documents/{document}/versions', [DocumentVersionController::class, 'index'])
            ->whereUuid('document');
        Route::get('documents/{document}/versions/{version}', [DocumentVersionController::class, 'show'])
            ->whereUuid('document')
            ->whereUuid('version');
        Route::match(['put', 'patch', 'delete'], 'documents/{document}/versions/{version}', fn () => abort(403, 'Los snapshots de documento son de solo inserción (append-only).'))
            ->whereUuid('document')
            ->whereUuid('version');

        // Auditoría
        Route::get('documents/{document}/audit', [AuditLogController::class, 'indexForDocument'])
            ->whereUuid('document');
        Route::get('templates/{template}/audit', [AuditLogController::class, 'indexForTemplate'])
            ->whereUuid('template');
        Route::get('comments/{comment}/audit', [AuditLogController::class, 'indexForComment'])
            ->whereUuid('comment');

        // Compartición de documentos
        Route::post('documents/{document}/shares', [DocumentShareController::class, 'store'])
            ->whereUuid('document');
        Route::delete('documents/{document}/shares/{userId}', [DocumentShareController::class, 'destroy'])
            ->whereUuid('document');

        // Revisión de documentos
        Route::get('documents/{document}/reviews', [ReviewController::class, 'index'])
            ->whereUuid('document');
        Route::post('documents/{document}/reviews/{review}/approve', [ReviewController::class, 'approve'])
            ->whereUuid('document')
            ->whereUuid('review');
        Route::post('documents/{document}/reviews/{review}/reject', [ReviewController::class, 'reject'])
            ->whereUuid('document')
            ->whereUuid('review');

        // Comentarios
        Route::apiResource('documents.comments', CommentController::class)
            ->except(['update'])
            ->shallow()
            ->whereUuid('document')
            ->whereUuid('comment');
        Route::apiResource('templates.comments', CommentController::class)
            ->except(['update'])
            ->shallow()
            ->whereUuid('template')
            ->whereUuid('comment');
        Route::patch('comments/{comment}/resolve', [CommentController::class, 'resolve'])
            ->whereUuid('comment');

        // Usuarios — búsqueda para asignación de revisores y compartición
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/reviewer-candidates', [UserController::class, 'reviewerCandidates']);
        Route::get('/users/document-reviewer-candidates', [UserController::class, 'documentReviewerCandidates']);

        // Dashboard (BFF)
        Route::get('/dashboard', [DashboardController::class, 'index']);

    });

});
