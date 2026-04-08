<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Services\Contracts\AuditLogServiceInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditLogService implements AuditLogServiceInterface
{
    public function __construct(
        private readonly AuditLogRepositoryInterface  $auditLogRepository,
        private readonly DocumentRepositoryInterface  $documentRepository,
        private readonly TemplateRepositoryInterface  $templateRepository,
        private readonly CommentRepositoryInterface   $commentRepository,
    ) {}

    /**
     * Registra una acción en el log de auditoría.
     *
     * El campo 'timestamp' no se incluye: PostgreSQL lo establece
     * con DEFAULT NOW() en el momento del INSERT.
     */
    public function record(
        string $entityType,
        string $entityId,
        string $action,
        string $userId,
        ?string $blockUuid = null,
        ?array $previousValue = null,
        ?array $newValue = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        return $this->auditLogRepository->create([
            'entity_type'    => $entityType,
            'entity_id'      => $entityId,
            'action'         => $action,
            'user_id'        => $userId,
            'block_uuid'     => $blockUuid,
            'previous_value' => $previousValue,
            'new_value'      => $newValue,
            'ip_address'     => $ipAddress,
            'user_agent'     => $userAgent,
        ]);
    }

    /**
     * Devuelve el historial de auditoría paginado para una entidad.
     */
    public function historyFor(
        string $entityType,
        string $entityId,
        int $perPage = 25,
    ): LengthAwarePaginator {
        return $this->auditLogRepository->paginateByEntity($entityType, $entityId, $perPage);
    }

    /**
     * Indica si el usuario puede acceder al historial de auditoría de una entidad.
     * La autorización por rol 'direccion' se resuelve en el Controller.
     */
    public function canUserAccess(string $entityType, string $entityId, string $userId): bool
    {
        return match ($entityType) {
            'document' => $this->documentRepository->isAuthorOrReviewer($entityId, $userId),
            'template' => $this->templateRepository->isCreatorOrReviewer($entityId, $userId),
            'comment'  => $this->commentRepository->isAuthorOrDocumentOwner($entityId, $userId),
            default    => false,
        };
    }
}
