<?php

declare(strict_types=1);

/**
 * Whitelist de fuentes disponibles para los themes. Cada entrada debe estar
 * realmente instalada en el container del backend (apk add en Dockerfile) —
 * lo que el frontend muestra coincide 1:1 con lo que WeasyPrint puede renderizar.
 *
 * Si añades una fuente aquí, también:
 *   1. Instálala en backend/Dockerfile (apk add font-…)
 *   2. Rebuild de la imagen + verifica con `fc-list : family`
 *   3. (Opcional) prueba un PDF con esa fuente
 *
 * Categorías:
 *   - sans  → cuerpo / encabezados modernos
 *   - serif → cuerpo formal / académico
 *   - mono  → bloques de código
 *
 * `stack` es el valor literal de CSS `font-family`. Incluye fallbacks por si
 * un weight concreto no está disponible.
 */

return [
    'sans' => [
        [
            'value' => 'Roboto Flex, Roboto, sans-serif',
            'label' => 'Roboto Flex',
            'note' => 'Variable font moderna, recomendada para encabezados',
        ],
        [
            'value' => 'Roboto, sans-serif',
            'label' => 'Roboto',
            'note' => 'Sans-serif neutra, muy legible en pantalla y papel',
        ],
        [
            'value' => 'Open Sans, sans-serif',
            'label' => 'Open Sans',
            'note' => 'Estándar académico, buena legibilidad',
        ],
        [
            'value' => 'Noto Sans, sans-serif',
            'label' => 'Noto Sans',
            'note' => 'Cobertura amplia de caracteres (multilingüe)',
        ],
        [
            'value' => 'Droid Sans, sans-serif',
            'label' => 'Droid Sans',
            'note' => 'Compacta, indicada para densidad alta de texto',
        ],
        [
            'value' => 'DejaVu Sans, Liberation Sans, sans-serif',
            'label' => 'DejaVu Sans',
            'note' => 'Fallback por defecto del sistema',
        ],
        [
            'value' => 'Liberation Sans, sans-serif',
            'label' => 'Liberation Sans',
            'note' => 'Equivalente a Arial (compatibilidad MS Office)',
        ],
    ],
    'serif' => [
        [
            'value' => 'Noto Serif, serif',
            'label' => 'Noto Serif',
            'note' => 'Serifa contemporánea, multilingüe',
        ],
        [
            'value' => 'DejaVu Serif, Liberation Serif, serif',
            'label' => 'DejaVu Serif',
            'note' => 'Serifa clásica',
        ],
        [
            'value' => 'Liberation Serif, serif',
            'label' => 'Liberation Serif',
            'note' => 'Equivalente a Times New Roman',
        ],
    ],
    'mono' => [
        [
            'value' => 'Roboto Mono, monospace',
            'label' => 'Roboto Mono',
            'note' => 'Para bloques de código',
        ],
        [
            'value' => 'Inconsolata, monospace',
            'label' => 'Inconsolata',
            'note' => 'Monoespacio compacto, alto contraste',
        ],
        [
            'value' => 'Mononoki, monospace',
            'label' => 'Mononoki',
            'note' => 'Para code blocks largos',
        ],
        [
            'value' => 'DejaVu Sans Mono, Liberation Mono, monospace',
            'label' => 'DejaVu Sans Mono',
            'note' => 'Fallback por defecto',
        ],
    ],
];
