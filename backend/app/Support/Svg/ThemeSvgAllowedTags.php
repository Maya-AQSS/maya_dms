<?php

declare(strict_types=1);

namespace App\Support\Svg;

use enshrined\svgSanitize\data\AllowedTags;
use enshrined\svgSanitize\data\TagInterface;

/**
 * DMS-B10b: allowlist de etiquetas para SVG de tema, derivada de la del
 * sanitizer pero SIN elementos que cargan recursos externos o referencian otros
 * nodos — los logos de tema son decorativos y no los necesitan. Esto preserva la
 * garantía «sin recursos remotos» del blocklist anterior (que bloqueaba
 * <image>/<use>/<foreignObject>) y evita SSRF al renderizar el SVG server-side
 * (export PDF).
 */
final class ThemeSvgAllowedTags implements TagInterface
{
    /** Etiquetas cargadoras de recursos / referenciadoras eliminadas de la allowlist. */
    private const DISALLOWED = ['image', 'a', 'use', 'foreignobject'];

    public static function getTags(): array
    {
        return array_values(array_filter(
            AllowedTags::getTags(),
            static fn (string $tag): bool => ! in_array(strtolower($tag), self::DISALLOWED, true),
        ));
    }
}
