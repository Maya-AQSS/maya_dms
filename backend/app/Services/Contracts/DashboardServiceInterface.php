<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Dashboard\DashboardDto;

interface DashboardServiceInterface
{
    /**
     * Devuelve payload BFF del dashboard para un usuario.
     */
    public function buildForUser(string $userId): DashboardDto;
}
