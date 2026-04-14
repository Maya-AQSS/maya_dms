<?php

namespace App\Services\Contracts;

use App\Models\AuditLog;
use Illuminate\Pagination\LengthAwarePaginator;

interface AuditLogServiceInterface
{
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
    ): AuditLog;

    public function historyFor(
        string $entityType,
        string $entityId,
        int $perPage = 25,
    ): LengthAwarePaginator;

    public function canUserAccess(string $entityType, string $entityId, string $userId): bool;
}
