<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\JwtUser;
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
        $this->auditLogService->assertCanAccessDocumentAudit($documentId, $this->jwtUser());

        return AuditLogResource::collection(
            $this->auditLogService->historyFor('document', $documentId)
        );
    }

    /**
     * Historial de auditoría de una plantilla.
     */
    public function indexForTemplate(string $templateId): ResourceCollection|JsonResponse
    {
        $this->auditLogService->assertCanAccessTemplateAudit($templateId, $this->jwtUser());

        return AuditLogResource::collection(
            $this->auditLogService->historyFor('template', $templateId)
        );
    }

    /**
     * Historial de auditoría de un comentario.
     */
    public function indexForComment(string $commentId): ResourceCollection|JsonResponse
    {
        $this->auditLogService->assertCanAccessCommentAudit($commentId, $this->jwtUser());

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
}
