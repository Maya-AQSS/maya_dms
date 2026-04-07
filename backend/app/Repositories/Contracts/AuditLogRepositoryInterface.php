<?php

namespace App\Repositories\Contracts;

use App\Models\AuditLog;
use Illuminate\Pagination\LengthAwarePaginator;

interface AuditLogRepositoryInterface
{
    public function create(array $data): AuditLog;

    public function paginateByEntity(
        string $entityType,
        string $entityId,
        int $perPage = 25,
    ): LengthAwarePaginator;
}
