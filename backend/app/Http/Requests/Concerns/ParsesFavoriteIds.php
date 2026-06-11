<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait ParsesFavoriteIds
{
    /**
     * Parsea `favorite_ids` (CSV de ids) a una lista, o null si vacío.
     *
     * @return list<string>|null
     */
    private function parseFavoriteIds(): ?array
    {
        $raw = $this->input('favorite_ids');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }
        $ids = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));

        return $ids === [] ? null : $ids;
    }
}
