<?php

namespace App\Repositories\Contracts;

use App\Models\TemplateVersion;
use Illuminate\Support\Collection;

interface TemplateVersionRepositoryInterface
{
    public function findOrFail(string $id): TemplateVersion;

    /**
     * Lista todas las versiones de una plantilla ordenadas por número de versión.
     *
     * @return Collection<int, TemplateVersion>
     */
    public function listForTemplateOrdered(string $templateId): Collection;

    /**
     * Obtiene el próximo número de versión para una plantilla.
     */
    public function nextVersionNumber(string $templateId): int;

    /**
     * Crea un snapshot de una plantilla.
     *
     * @param  array<int, array<string, mixed>>  $blocksSnapshot
     */
    public function createSnapshot(
        string $templateId,
        int $versionNumber,
        array $blocksSnapshot,
        string $changelog,
        string $publishedBy,
    ): TemplateVersion;
}
