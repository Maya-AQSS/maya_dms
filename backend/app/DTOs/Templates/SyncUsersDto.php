<?php

declare(strict_types=1);

namespace App\DTOs\Templates;

/**
 * Datos para sincronizar una lista de usuarios (revisores de plantilla o
 * validadores de documento). El array ya viene deduplicado desde el Request.
 */
readonly class SyncUsersDto
{
    /**
     * @param  list<string>  $userIds
     */
    public function __construct(
        public array $userIds,
    ) {}
}
