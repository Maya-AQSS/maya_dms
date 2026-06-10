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
            // Regiones materializadas (mm, A4 210×297) para que el tema por defecto
            // tenga componentes visibles/editables/clonables en el editor y el preview
            // — antes estaba vacío y solo el "chrome estándar" CSS pintaba algo.
            // El content_slot define los márgenes (top/bottom 2.5cm, laterales 2cm).
            'regions' => [
                [
                    'id' => 'default-content',
                    'type' => 'content_slot',
                    'box' => ['x' => 20, 'y' => 25, 'w' => 170, 'h' => 247, 'z' => 1],
                    'props' => ['label' => 'Aquí se carga el cuerpo del documento'],
                ],
                [
                    'id' => 'default-header',
                    'type' => 'text',
                    'box' => ['x' => 20, 'y' => 13, 'w' => 170, 'h' => 8, 'z' => 2],
                    'props' => ['text' => 'CEEDCV', 'size' => 9, 'color' => '#0b5394', 'align' => 'right'],
                ],
                [
                    'id' => 'default-page-number',
                    'type' => 'page_number',
                    'box' => ['x' => 145, 'y' => 282, 'w' => 45, 'h' => 8, 'z' => 2],
                    'props' => ['format' => 'page-of-pages', 'align' => 'right'],
                ],
            ],
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
