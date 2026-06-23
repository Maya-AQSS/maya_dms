<?php

declare(strict_types=1);

namespace App\Support\Svg;

use enshrined\svgSanitize\data\AllowedAttributes;
use enshrined\svgSanitize\data\AttributeInterface;

/**
 * DMS-B10b: allowlist de atributos para SVG de tema, derivada de la del
 * sanitizer pero SIN `href`/`xlink:href` — un logo decorativo no necesita
 * hipervínculos ni referencias externas, y así se elimina el vector de recursos
 * remotos (que el sanitizer base deja pasar en `<tag href="http://…">`).
 */
final class ThemeSvgAllowedAttributes implements AttributeInterface
{
    private const DISALLOWED = ['href', 'xlink:href'];

    public static function getAttributes(): array
    {
        return array_values(array_filter(
            AllowedAttributes::getAttributes(),
            static fn (string $attr): bool => ! in_array(strtolower($attr), self::DISALLOWED, true),
        ));
    }
}
