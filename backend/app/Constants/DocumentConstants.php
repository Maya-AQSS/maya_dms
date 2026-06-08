<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Constantes compartidas de renderizado de documentos y plantillas.
 */
final class DocumentConstants
{
    /**
     * Theme por defecto cuando el documento o plantilla no tiene theme asignado.
     * Shape idéntico al del StoreThemeRequest para consistencia visual.
     *
     * @var array<string, mixed>
     */
    public const DEFAULT_THEME = [
        'palette' => [
            'primary' => '#0b5394',
            'secondary' => '#666666',
            'text' => '#1a1a1a',
            'background' => '#ffffff',
            'accent' => '#f59e0b',
        ],
        'typography' => [
            'heading_font' => 'DejaVu Sans, Liberation Sans, sans-serif',
            'body_font' => 'DejaVu Sans, Liberation Sans, sans-serif',
            'base_size_pt' => 11,
            'line_height' => 1.5,
        ],
        'layout' => [
            'regions' => [],
            'page' => ['size' => 'A4', 'margin_cm' => ['top' => 2.5, 'right' => 2, 'bottom' => 2.5, 'left' => 2]],
        ],
        'accessibility' => [
            'language' => 'es',
            'title' => null,
            'subject' => null,
            'author' => 'CEEDCV',
        ],
        'brand_name' => 'CEEDCV',
    ];
}
