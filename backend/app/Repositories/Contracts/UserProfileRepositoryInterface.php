<?php

namespace App\Repositories\Contracts;

interface UserProfileRepositoryInterface
{
    /**
     * Perfil del usuario desde la vista FDW (`users`), siempre filtrado por id.
     */
    public function findById(string $userId): ?array;

    /**
     * Grupos académicos del usuario; el JOIN incluye filtro por user_id.
     *
     * @return array<int, object>
     */
    public function findGroupsByUserId(string $userId): array;
}
