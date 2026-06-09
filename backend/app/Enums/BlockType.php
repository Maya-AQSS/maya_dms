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

    /**
     * ¿Este tipo de bloque lleva cuerpo editable cuyo vacío debe validarse?
     *
     * Los bloques estructurales (portada, índice, hoja en blanco) están exentos
     * de la invariante "los bloques bloqueados/modificables no pueden estar
     * vacíos": no tienen cuerpo Tiptap (la portada usa geometría, el índice se
     * autogenera, la hoja en blanco está vacía por definición). Espejo de
     * `blockTypeRequiresContent()` en el frontend. Para añadir un futuro tipo sin
     * cuerpo, devuélvelo aquí como `false`.
     */
    public function requiresBodyContent(): bool
    {
        return match ($this) {
            self::Cover, self::Index, self::Blank => false,
            default => true,
        };
    }
}
