<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait ParsesCsvIds
{
    /**
     * Parsea un parámetro CSV de ids a lista, o null si vacío.
     *
     * @return list<string>|null
     */
    private function parseCsvIds(?string $key): ?array
    {
        $raw = $this->input($key);
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $ids = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));

        return $ids === [] ? null : $ids;
    }
}
