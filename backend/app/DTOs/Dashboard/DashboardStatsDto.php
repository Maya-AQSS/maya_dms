<?php

declare(strict_types=1);

namespace App\DTOs\Dashboard;

/**
 * Contadores agregados del dashboard por severidad (críticos / altos).
 */
final readonly class DashboardStatsDto
{
    public function __construct(
        public int $documentsCritical,
        public int $documentsHigh,
        public int $templatesCritical,
        public int $templatesHigh,
    ) {}

    /**
     * @return array{
     *     documents_critical: int,
     *     documents_high: int,
     *     templates_critical: int,
     *     templates_high: int,
     * }
     */
    public function toArray(): array
    {
        return [
            'documents_critical' => $this->documentsCritical,
            'documents_high' => $this->documentsHigh,
            'templates_critical' => $this->templatesCritical,
            'templates_high' => $this->templatesHigh,
        ];
    }
}
