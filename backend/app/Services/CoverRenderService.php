<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Renderiza un bloque "portada" (cover) a HTML con elementos posicionados de
 * forma absoluta en cm sobre la página completa.
 *
 * Modelo de datos:
 *   - La GEOMETRÍA vive en el `default_content` del bloque de plantilla:
 *       { kind:'cover', page:{size}, regions: [ {id, type, box:{x,y,w,h,z}, props} ] }
 *     donde `box` está en MILÍMETROS (igual que el editor `AbsoluteCanvas`).
 *   - Los VALORES de los placeholders de texto viven en el `content` del bloque
 *     de documento: { kind:'cover-fill', values: { <key>: <texto> } }.
 *
 * Tipos de región: text · image · date · page_number · text_placeholder.
 * Sólo `text_placeholder` admite valor por documento; el resto es estático.
 *
 * Los assets de imagen se resuelven igual que en el Blade `documents.render`:
 *   - preview (iframe blob): `data:` URI base64.
 *   - PDF (WeasyPrint): `file://` del filesystem del contenedor.
 */
class CoverRenderService
{
    /**
     * Devuelve el HTML interno de la sección de portada (los elementos
     * absolutos). El wrapper `<section class="doc-block doc-block--cover">` lo
     * añade el servicio de render que la invoca.
     *
     * @param  array<string, mixed>  $cover  default_content del bloque (geometría)
     * @param  array<string, string>  $values  valores de placeholders por key
     */
    public function renderInner(array $cover, array $values, bool $previewMode, string $lang = 'es'): string
    {
        $regions = $cover['regions'] ?? [];
        if (! is_array($regions)) {
            return '';
        }

        $parts = [];
        foreach ($regions as $region) {
            if (! is_array($region) || ! isset($region['box']) || ! is_array($region['box'])) {
                continue;
            }
            $parts[] = $this->renderRegion($region, $values, $previewMode, $lang);
        }

        return implode('', array_filter($parts));
    }

    /**
     * @param  array<string, mixed>  $region
     * @param  array<string, string>  $values
     */
    private function renderRegion(array $region, array $values, bool $previewMode, string $lang): string
    {
        $box = $region['box'];
        $style = $this->boxStyle($box);
        $props = isset($region['props']) && is_array($region['props']) ? $region['props'] : [];
        $type = (string) ($region['type'] ?? 'text');

        return match ($type) {
            'text' => $this->renderText($style, $props, (string) ($props['text'] ?? '')),
            'text_placeholder' => $this->renderPlaceholder($style, $props, $values),
            'image' => $this->renderImage($style, $props, $previewMode),
            'date' => $this->renderDate($style, $props, $lang),
            'page_number' => $this->renderPageNumber($style, $props),
            default => '',
        };
    }

    /**
     * Estilo de posición absoluta (cm) desde la caja en mm.
     *
     * @param  array<string, mixed>  $box
     */
    private function boxStyle(array $box): string
    {
        $cm = fn (mixed $v) => number_format(((float) $v) / 10.0, 4, '.', '').'cm';

        return sprintf(
            'position:absolute;left:%s;top:%s;width:%s;height:%s;z-index:%d;',
            $cm($box['x'] ?? 0),
            $cm($box['y'] ?? 0),
            $cm($box['w'] ?? 0),
            $cm($box['h'] ?? 0),
            (int) ($box['z'] ?? 1),
        );
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function textStyle(array $props): string
    {
        $align = $this->normalizeAlign((string) ($props['align'] ?? 'left'), 'left');
        $weight = ($props['weight'] ?? '') === 'bold' ? 'font-weight:700;' : '';
        $color = $this->safeColor((string) ($props['color'] ?? '#1a1a1a'));
        $size = (float) ($props['size'] ?? 12);

        return sprintf(
            'font-size:%spt;color:%s;text-align:%s;%s',
            number_format($size, 2, '.', ''),
            $color,
            $align,
            $weight,
        );
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderText(string $boxStyle, array $props, string $text): string
    {
        return sprintf(
            '<div class="cover-el cover-el--text" style="%s%s">%s</div>',
            $boxStyle,
            $this->textStyle($props),
            nl2br(e($text)),
        );
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, string>  $values
     */
    private function renderPlaceholder(string $boxStyle, array $props, array $values): string
    {
        $key = (string) ($props['key'] ?? '');
        $value = $key !== '' && isset($values[$key]) ? (string) $values[$key] : '';
        if ($value === '') {
            $value = (string) ($props['defaultText'] ?? '');
        }

        return sprintf(
            '<div class="cover-el cover-el--placeholder" style="%s%s">%s</div>',
            $boxStyle,
            $this->textStyle($props),
            nl2br(e($value)),
        );
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderImage(string $boxStyle, array $props, bool $previewMode): string
    {
        $url = MediaAssetResolver::resolve(isset($props['src']) ? (string) $props['src'] : null, $previewMode);
        if ($url === null) {
            return '';
        }
        $opacity = max(0.0, min(1.0, (float) ($props['opacity'] ?? 1)));
        $fit = in_array($props['objectFit'] ?? 'contain', ['cover', 'contain', 'fill'], true)
            ? (string) ($props['objectFit'] ?? 'contain')
            : 'contain';

        return sprintf(
            '<div class="cover-el cover-el--image" style="%sopacity:%s;"><img src="%s" alt="%s" style="width:100%%;height:100%%;object-fit:%s;"></div>',
            $boxStyle,
            number_format($opacity, 2, '.', ''),
            e($url),
            e((string) ($props['alt'] ?? '')),
            $fit,
        );
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderDate(string $boxStyle, array $props, string $lang): string
    {
        $text = ($props['format'] ?? 'short') === 'long'
            ? Carbon::now()->locale($lang)->isoFormat('D [de] MMMM [de] YYYY')
            : Carbon::now()->format('d/m/Y');

        return sprintf(
            '<div class="cover-el cover-el--date" style="%s%s">%s</div>',
            $boxStyle,
            $this->textStyle($props),
            e($text),
        );
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderPageNumber(string $boxStyle, array $props): string
    {
        // counter(page)/counter(pages) vía CSS (.cover-pn/.cover-pt) — funciona
        // tanto en WeasyPrint como en paged.js dentro del flujo del documento.
        $inner = ($props['format'] ?? 'page-of-pages') === 'page-of-pages'
            ? 'Página <span class="cover-pn"></span> de <span class="cover-pt"></span>'
            : 'Página <span class="cover-pn"></span>';

        return sprintf(
            '<div class="cover-el cover-el--meta" style="%s%s">%s</div>',
            $boxStyle,
            $this->textStyle($props),
            $inner,
        );
    }

    private function normalizeAlign(string $align, string $default): string
    {
        return in_array($align, ['left', 'center', 'right'], true) ? $align : $default;
    }

    private function safeColor(string $color): string
    {
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) === 1 ? $color : '#1a1a1a';
    }
}
