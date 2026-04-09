<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Services\Contracts\AuditLogServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogServiceInterface $auditLogService,
    ) {}

    /**
     * Historial de auditoría de un documento.
     * Acceso: autor (owner_id / created_by), revisor asignado o rol 'direccion'.
     */
    public function indexForDocument(Request $request, string $documentId): ResourceCollection|JsonResponse
    {
        /** @var \App\Models\JwtUser $user */
        $user = auth()->user();

        // Si el usuario no es de dirección, validamos acceso.
        // Usar findOrFail asegura que si el recurso no es visible para el scope global, devuelva 404 (IDOR protection)
        if (! $user->hasRole('direccion')) {
            \App\Models\Document::findOrFail($documentId);
        }

        return AuditLogResource::collection(
            $this->auditLogService->historyFor('document', $documentId)
        );
    }

    /**
     * Historial de auditoría de una plantilla.
     * Acceso: creador, revisor asignado o rol 'direccion'.
     */
    public function indexForTemplate(Request $request, string $templateId): ResourceCollection|JsonResponse
    {
        /** @var \App\Models\JwtUser $user */
        $user = auth()->user();

        if (! $user->hasRole('direccion')) {
            \App\Models\Template::findOrFail($templateId);
        }

        return AuditLogResource::collection(
            $this->auditLogService->historyFor('template', $templateId)
        );
    }

    /**
     * Historial de auditoría de un comentario.
     * Acceso: autor del comentario, propietario/creador del documento padre o rol 'direccion'.
     */
    public function indexForComment(Request $request, string $commentId): ResourceCollection|JsonResponse
    {
        /** @var \App\Models\JwtUser $user */
        $user = auth()->user();

        if (! $user->hasRole('direccion')) {
            \App\Models\Comment::findOrFail($commentId);
        }

        return AuditLogResource::collection(
            $this->auditLogService->historyFor('comment', $commentId)
        );
    }
}
