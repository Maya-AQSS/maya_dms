<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface DashboardServiceInterface
{
    /**
     * Devuelve payload BFF del dashboard para un usuario.
     *
     * @return array<string, mixed>
     */
    public function buildForUser(string $userId): array;
}
