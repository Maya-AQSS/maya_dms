<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Familia de "bloques de maquetación" (layout blocks). Determina cómo se edita
 * el bloque en la plantilla y cómo se renderiza en el PDF.
 *
 * - Content: bloque normal de contenido (por defecto, comportamiento actual).
 * - Cover:   portada (maquetación posicionada; sin tema por defecto).
 * - Blank:   hoja en blanco (página vacía intencional).
 * - Index:   índice/TOC generado desde los títulos del documento.
 */
enum BlockType: string
{
    case Content = 'content';
    case Cover = 'cover';
    case Blank = 'blank';
    case Index = 'index';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
