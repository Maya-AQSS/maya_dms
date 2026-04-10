<?php

namespace App\Repositories\Eloquent;

use App\Models\TemplateVersion;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TemplateVersionRepository implements TemplateVersionRepositoryInterface
{
    /**
     * Localiza una versión de plantilla por su ID.
     */
    public function findOrFail(string $id): TemplateVersion
    {
        return TemplateVersion::query()->findOrFail($id);
    }

    /**
     * Última versión publicada de la plantilla (mayor {@see TemplateVersion::$version_number}), o null.
     */
    public function findLatestPublishedForTemplate(string $templateId): ?TemplateVersion
    {
        return TemplateVersion::query()
            ->where('template_id', $templateId)
            ->orderByDesc('version_number')
            ->first();
    }

    /**
     * Lista todas las versiones de una plantilla ordenadas por número de versión.
     */
    public function listForTemplateOrdered(string $templateId): Collection
    {
        return TemplateVersion::query()
            ->where('template_id', $templateId)
            ->orderBy('version_number')
            ->get();
    }

    /**
     * Obtiene el próximo número de versión para una plantilla.
     */
    public function nextVersionNumber(string $templateId): int
    {
        $max = TemplateVersion::query()
            ->where('template_id', $templateId)
            ->max('version_number');

        return (int) $max + 1;
    }

    /**
     * Crea un snapshot de una plantilla.
     */
    public function createSnapshot(
        string $templateId,
        int $versionNumber,
        array $blocksSnapshot,
        string $changelog,
        string $publishedBy,
    ): TemplateVersion {
        $now = now();

        return TemplateVersion::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $templateId,
            'version_number' => $versionNumber,
            'blocks_snapshot' => $blocksSnapshot,
            'changelog' => $changelog,
            'published_by' => $publishedBy,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
