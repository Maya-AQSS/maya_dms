<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Services\Contracts\AuditLogServiceInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditLogService implements AuditLogServiceInterface
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogRepository,
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
        ?string $blockId = null,
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
            'block_id'       => $blockId,
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
}
