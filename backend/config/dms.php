<?php

declare(strict_types=1);

/*
 * Configuración propia del módulo DMS.
 *
 * Flags de funcionalidad para rollouts controlados. Cada flag se puede
 * activar/desactivar por entorno vía `.env`.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Special Blocks (cover / blank / toc)
    |--------------------------------------------------------------------------
    |
    | Activa los bloques especiales de página completa: portada, página en
    | blanco e índice (TOC) auto-generado. Cuando está desactivado:
    |   - El middleware EnsureSpecialBlocksEnabled rechaza con 403 cualquier
    |     intento de crear/actualizar un bloque con kind != 'content'.
    |   - El frontend oculta los 3 botones del catálogo (vía
    |     VITE_DMS_SPECIAL_BLOCKS_ENABLED en Vite).
    |   - Bloques pre-existentes con kind especial siguen rindiéndose
    |     normalmente (el flag solo controla creación).
    |
    | Para rollback total: desactivar este flag y, si fuera necesario, hacer
    | soft-delete de los bloques con kind != 'content'.
    */
    'special_blocks_enabled' => env('DMS_SPECIAL_BLOCKS_ENABLED', false),
];
