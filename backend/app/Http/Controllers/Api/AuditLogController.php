<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Services\Contracts\AuditLogServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Document;
use App\Models\Template;
use App\Models\Comment;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogServiceInterface $auditLogService,
    ) {}

    /**
     * Historial de auditoría de un documento.
     */
    public function indexForDocument(Request $request, string $documentId): ResourceCollection|JsonResponse
    {
        /** @var \App\Models\JwtUser $user */
        $user = auth()->user();
        if (! $user->hasPermission('audit.read')) {
            Document::findOrFail($documentId);
        }

        return AuditLogResource::collection(
            $this->auditLogService->historyFor('document', $documentId)
        );
    }

    /**
     * Historial de auditoría de una plantilla.
     */
    public function indexForTemplate(Request $request, string $templateId): ResourceCollection|JsonResponse
    {
        /** @var \App\Models\JwtUser $user */
        $user = auth()->user();

        if (! $user->hasPermission('audit.read')) {
            Template::findOrFail($templateId);
        }

        return AuditLogResource::collection(
            $this->auditLogService->historyFor('template', $templateId)
        );
    }

    /**
     * Historial de auditoría de un comentario.
     */
    public function indexForComment(Request $request, string $commentId): ResourceCollection|JsonResponse
    {
        /** @var \App\Models\JwtUser $user */
        $user = auth()->user();

        if (! $user->hasPermission('audit.read')) {
            Comment::findOrFail($commentId);
        }

        return AuditLogResource::collection(
            $this->auditLogService->historyFor('comment', $commentId)
        );
    }
}
