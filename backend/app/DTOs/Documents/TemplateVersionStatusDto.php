<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

use JsonSerializable;

/**
 * Resultado de la comparación ligera entre la versión de plantilla anclada al
 * documento y la última publicada.
 * Devuelto por DocumentService::templateVersionStatus().
 */
final readonly class TemplateVersionStatusDto implements JsonSerializable
{
    /**
     * @param  array{id: string, version_number: int}|null  $currentVersion
     * @param  array{id: string, version_number: int, changelog: string}|null  $latestVersion
     */
    public function __construct(
        public ?array $currentVersion,
        public ?array $latestVersion,
        public bool $hasUpdate,
        public ?string $changelog,
    ) {}

    /**
     * @return array{
     *   current_version: array{id: string, version_number: int}|null,
     *   latest_version: array{id: string, version_number: int, changelog: string}|null,
     *   has_update: bool,
     *   changelog: string|null
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'current_version' => $this->currentVersion,
            'latest_version' => $this->latestVersion,
            'has_update' => $this->hasUpdate,
            'changelog' => $this->changelog,
        ];
    }
}
