<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Discriminador del tipo de bloque dentro de una plantilla / documento.
 *
 * - Content: bloque de contenido normal (BlockNote), fluye, hereda theme.
 * - Cover:   página de portada (lienzo A4 limpio, sin chrome del theme).
 * - Blank:   página en blanco intencional (sin chrome del theme).
 * - Toc:     marker de índice; el HTML del índice se genera en render-time
 *            a partir de los headings de los bloques `content` previos.
 *
 * La paginación (salto de página antes y después) se aplica desde CSS Paged
 * Media para Cover/Blank/Toc — no se persiste flag de break en BD.
 */
enum BlockKind: string
{
    case Content = 'content';
    case Cover = 'cover';
    case Blank = 'blank';
    case Toc = 'toc';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
