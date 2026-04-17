<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\Comment;
use App\Models\Document;
use App\Models\JwtUser;
use App\Models\Template;
use App\Services\Contracts\AuditLogServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogServiceInterface $auditLogService,
    ) {}

    /**
     * Historial de auditoría de un documento.
     */
    public function indexForDocument(string $documentId): ResourceCollection|JsonResponse
    {
        $this->assertCanAccessDocumentAudit($documentId);

        return AuditLogResource::collection(
            $this->auditLogService->historyFor('document', $documentId)
        );
    }

    /**
     * Historial de auditoría de una plantilla.
     */
    public function indexForTemplate(string $templateId): ResourceCollection|JsonResponse
    {
        $this->assertCanAccessTemplateAudit($templateId);

        return AuditLogResource::collection(
            $this->auditLogService->historyFor('template', $templateId)
        );
    }

    /**
     * Historial de auditoría de un comentario.
     */
    public function indexForComment(string $commentId): ResourceCollection|JsonResponse
    {
        $this->assertCanAccessCommentAudit($commentId);

        return AuditLogResource::collection(
            $this->auditLogService->historyFor('comment', $commentId)
        );
    }

    private function jwtUser(): JwtUser
    {
        $user = auth()->user();
        if (! $user instanceof JwtUser) {
            abort(403);
        }

        return $user;
    }

    /**
     * Participante (alcance del modelo) o permiso `audit.read` en BD (lectura elevada).
     */
    private function assertCanAccessDocumentAudit(string $documentId): void
    {
        if (Document::query()->whereKey($documentId)->exists()) {
            return;
        }

        if (! $this->jwtUser()->hasPermission('audit.read')) {
            abort(404);
        }

        Document::query()->withoutGlobalScopes(['user_access'])->findOrFail($documentId);
    }

    private function assertCanAccessTemplateAudit(string $templateId): void
    {
        if (Template::query()->whereKey($templateId)->exists()) {
            return;
        }

        if (! $this->jwtUser()->hasPermission('audit.read')) {
            abort(404);
        }

        Template::query()->withoutGlobalScopes(['user_access'])->findOrFail($templateId);
    }

    private function assertCanAccessCommentAudit(string $commentId): void
    {
        if (Comment::query()->whereKey($commentId)->exists()) {
            return;
        }

        if (! $this->jwtUser()->hasPermission('audit.read')) {
            abort(404);
        }

        Comment::query()->withoutGlobalScopes(['user_access'])->findOrFail($commentId);
    }
}
