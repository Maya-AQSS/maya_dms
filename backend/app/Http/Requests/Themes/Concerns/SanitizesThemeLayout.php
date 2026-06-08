<?php

declare(strict_types=1);

namespace App\Http\Requests\Themes\Concerns;

/**
 * Sanitiza campos derivados (solo lectura) del layout antes de persistencia.
 */
trait SanitizesThemeLayout
{
    /**
     * Devuelve copia del layout sin los campos derivados (srcUrl, etc).
     * No muta el input.
     *
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    protected function stripDerivedLayoutFields(array $layout): array
    {
        if (empty($layout['regions']) || ! is_array($layout['regions'])) {
            return $layout;
        }

        // Copia inmutable: map + array_replace
        $cleanRegions = array_map(function (array $region) {
            if (empty($region['props']) || ! is_array($region['props'])) {
                return $region;
            }

            // Copia del props sin srcUrl
            $cleanProps = array_filter(
                $region['props'],
                fn ($key) => $key !== 'srcUrl',
                ARRAY_FILTER_USE_KEY
            );

            return array_replace($region, ['props' => $cleanProps]);
        }, $layout['regions']);

        return array_replace($layout, ['regions' => $cleanRegions]);
    }
}
