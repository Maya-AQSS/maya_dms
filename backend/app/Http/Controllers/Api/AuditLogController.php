<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Historial de auditoría de un documento.
     * Acceso: autor (owner_id / created_by), revisor asignado o rol 'direccion'.
     */
    public function indexForDocument(Request $request, string $documentId): JsonResponse
    {
        /** @var \App\Models\JwtUser $user */
        $user = auth()->user();

        if (! $user->hasRole('direccion') && ! $this->auditLogService->canUserAccess('document', $documentId, $user->id)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return response()->json(
            $this->auditLogService->historyFor('document', $documentId)
        );
    }

    /**
     * Historial de auditoría de una plantilla.
     * Acceso: creador, revisor asignado o rol 'direccion'.
     */
    public function indexForTemplate(Request $request, string $templateId): JsonResponse
    {
        /** @var \App\Models\JwtUser $user */
        $user = auth()->user();

        if (! $user->hasRole('direccion') && ! $this->auditLogService->canUserAccess('template', $templateId, $user->id)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return response()->json(
            $this->auditLogService->historyFor('template', $templateId)
        );
    }

    /**
     * Historial de auditoría de un comentario.
     * Acceso: autor del comentario, propietario/creador del documento padre o rol 'direccion'.
     */
    public function indexForComment(Request $request, string $commentId): JsonResponse
    {
        /** @var \App\Models\JwtUser $user */
        $user = auth()->user();

        if (! $user->hasRole('direccion') && ! $this->auditLogService->canUserAccess('comment', $commentId, $user->id)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return response()->json(
            $this->auditLogService->historyFor('comment', $commentId)
        );
    }
}
