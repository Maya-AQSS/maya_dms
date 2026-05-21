<?php

use App\Http\Controllers\Api\AcademicHierarchyController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentBlockController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\DocumentOptionsController;
use App\Http\Controllers\Api\DocumentShareController;
use App\Http\Controllers\Api\DocumentStateController;
use App\Http\Controllers\Api\DocumentVersionController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\ProcessController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\TemplateBlockBulkController;
use App\Http\Controllers\Api\TemplateBlockController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\TemplateReviewersController;
use App\Http\Controllers\Api\TemplateStateController;
use App\Http\Controllers\Api\TemplateVersionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;
use Maya\Profile\Routing\MeRoutes;

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

    // Perfil: solo JWT (sin dms.login). El front necesita /me para resolver permisos.
    Route::middleware(['jwt'])->group(function () {
        MeRoutes::register();
    });

    // ── Rutas protegidas por JWT + permiso de acceso a la app ──
    Route::middleware(['jwt', 'permission:dms.login'])->group(function () {

        Route::get('/hierarchy', [AcademicHierarchyController::class, 'index']);

        Route::get('/processes', [ProcessController::class, 'index']);
        Route::post('/processes', [ProcessController::class, 'store']);
        Route::get('/processes/{process}', [ProcessController::class, 'show'])->whereUuid('process');
        Route::match(['put', 'patch'], '/processes/{process}', [ProcessController::class, 'update'])->whereUuid('process');
        Route::delete('/processes/{process}', [ProcessController::class, 'destroy'])->whereUuid('process');

        // Media — subida de imágenes para bloques BlockNote
        Route::post('media', [MediaController::class, 'store']);

        // Plantillas
        // Combinación de validación UUID (develop) y actualización masiva de bloques (feature).
        Route::apiResource('templates', TemplateController::class)
            ->whereUuid('template');
        Route::post('templates/{template}/reviewers', [TemplateReviewersController::class, 'syncReviewers'])
            ->whereUuid('template');
        Route::post('templates/{template}/document-reviewers', [TemplateReviewersController::class, 'syncDocumentReviewers'])
            ->whereUuid('template');
        Route::put('blocks/bulk', [TemplateBlockBulkController::class, 'bulkUpdate']);
        Route::patch('templates/{template}/blocks/reorder', [TemplateBlockBulkController::class, 'reorder'])
            ->whereUuid('template');

        Route::apiResource('templates.blocks', TemplateBlockController::class)
            ->shallow()
            ->whereUuid('template')
            ->whereUuid('block');
        Route::post('templates/{template}/clone', [TemplateController::class, 'clone'])
            ->whereUuid('template');
        Route::post('templates/{template}/submit-review', [TemplateStateController::class, 'submitForReview'])
            ->whereUuid('template');
        Route::post('templates/{template}/reject-review', [TemplateStateController::class, 'rejectReview'])
            ->whereUuid('template');
        Route::post('templates/{template}/approve-review', [TemplateStateController::class, 'approveReview'])
            ->whereUuid('template');
        Route::post('templates/{template}/publish', [TemplateStateController::class, 'publish'])
            ->whereUuid('template');
        Route::post('templates/{template}/new-version', [TemplateStateController::class, 'startNewVersion'])
            ->whereUuid('template');
        Route::delete('templates/{template}/versions/{version}', [TemplateStateController::class, 'destroyVersion'])
            ->whereUuid('template')
            ->whereUuid('version');
        Route::get('templates/{template}/versions', [TemplateVersionController::class, 'index'])
            ->whereUuid('template');
        Route::get('template-versions/{template_version}', [TemplateVersionController::class, 'show'])
            ->whereUuid('template_version');
        Route::match(['put', 'patch', 'delete'], 'template-versions/{template_version}', fn () => abort(403, 'Los snapshots de plantilla son de solo inserción (append-only).'))
            ->whereUuid('template_version');

        // Documentos — lookups y opciones (DocumentOptionsController)
        Route::get('documents/creation-options', [DocumentOptionsController::class, 'creationOptions']);
        Route::post('documents/create-from-module', [DocumentOptionsController::class, 'createFromModule']);
        Route::get('documents/{document}/template-version-status', [DocumentOptionsController::class, 'templateVersionStatus'])
            ->whereUuid('document');

        // Documentos — CRUD (DocumentController)
        Route::get('documents', [DocumentController::class, 'index']);
        Route::post('documents', [DocumentController::class, 'store']);
        Route::post('documents/{document}/clone', [DocumentController::class, 'clone'])
            ->whereUuid('document');
        Route::get('documents/{document}', [DocumentController::class, 'show'])
            ->whereUuid('document');
        Route::match(['put', 'patch'], 'documents/{document}', [DocumentController::class, 'update'])
            ->whereUuid('document');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])
            ->whereUuid('document');

        // Documentos — transiciones de estado (DocumentStateController)
        Route::post('documents/{document}/submit', [DocumentStateController::class, 'submit'])
            ->whereUuid('document');
        Route::post('documents/{document}/publish', [DocumentStateController::class, 'publish'])
            ->whereUuid('document');
        Route::post('documents/{document}/new-version', [DocumentStateController::class, 'startNewVersion'])
            ->whereUuid('document');
        Route::post('documents/{document}/delegate', [DocumentStateController::class, 'delegate'])
            ->whereUuid('document');

        Route::get('documents/{document}/blocks', [DocumentBlockController::class, 'index'])
            ->whereUuid('document');
        Route::put('documents/{document}/blocks/{block}', [DocumentBlockController::class, 'update'])
            ->whereUuid('document')
            ->whereUuid('block');
        Route::delete('documents/{document}/blocks/{block}', [DocumentBlockController::class, 'destroy'])
            ->whereUuid('document')
            ->whereUuid('block');

        Route::get('documents/{document}/versions', [DocumentVersionController::class, 'index'])
            ->whereUuid('document');
        Route::get('documents/{document}/versions/{version}', [DocumentVersionController::class, 'show'])
            ->whereUuid('document')
            ->whereUuid('version');
        Route::delete('documents/{document}/versions/{version}', [DocumentStateController::class, 'destroyVersion'])
            ->whereUuid('document')
            ->whereUuid('version');
        Route::match(['put', 'patch'], 'documents/{document}/versions/{version}', fn () => abort(403, 'Los snapshots de documento son de solo inserción (append-only).'))
            ->whereUuid('document')
            ->whereUuid('version');

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
        // Usuarios — búsqueda para asignación de revisores y compartición
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/reviewer-candidates', [UserController::class, 'reviewerCandidates']);
        Route::get('/users/document-reviewer-candidates', [UserController::class, 'documentReviewerCandidates']);

        // Dashboard (BFF): listados del panel principal
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('permission:dms.index');

        // Favoritos (plantillas y documentos)
        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::post('/favorites/templates/{template}', [FavoriteController::class, 'storeTemplate'])
            ->whereUuid('template');
        Route::delete('/favorites/templates/{template}', [FavoriteController::class, 'destroyTemplate'])
            ->whereUuid('template');
        Route::post('/favorites/documents/{document}', [FavoriteController::class, 'storeDocument'])
            ->whereUuid('document');
        Route::delete('/favorites/documents/{document}', [FavoriteController::class, 'destroyDocument'])
            ->whereUuid('document');

    });

});
