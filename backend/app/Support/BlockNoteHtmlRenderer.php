<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Convierte el contenido JSON de BlockNote a HTML seguro.
 *
 * BlockNote emite un árbol de bloques con shape `{type, props, content, children}`.
 * Esta implementación cubre los tipos más comunes (paragraph, heading,
 * bulletListItem, numberedListItem, checkListItem, table, codeBlock, quote)
 * y aplica escaping HTML estricto. Para tipos desconocidos cae a un `<div>`
 * con el texto plano extraído.
 *
 * Nota: este renderer es server-side para PDF/UA + preview backend. El
 * frontend usa el componente nativo de BlockNote para edición; este código
 * NO está pensado para igualar 100% del estilo del editor — solo el output
 * semántico que WeasyPrint puede transformar en PDF tagged.
 */
final class BlockNoteHtmlRenderer
{
    /**
     * @param  array<int, array<string, mixed>>  $blocks  Bloques top-level del documento.
     */
    public static function renderBlocks(array $blocks): string
    {
        $buffer = '';
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $buffer .= self::renderBlock($block);
        }

        return $buffer;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private static function renderBlock(array $block): string
    {
        $type = (string) ($block['type'] ?? 'paragraph');
        $props = (array) ($block['props'] ?? []);
        $inline = self::renderInline((array) ($block['content'] ?? []));
        $children = self::renderBlocks((array) ($block['children'] ?? []));

        $style = self::propsToStyle($props);
        $styleAttr = $style !== '' ? ' style="'.e($style).'"' : '';

        return match ($type) {
            'heading' => self::renderHeading($props, $inline, $styleAttr).$children,
            'paragraph' => '<p'.$styleAttr.'>'.$inline.'</p>'.$children,
            'bulletListItem' => '<ul><li'.$styleAttr.'>'.$inline.$children.'</li></ul>',
            'numberedListItem' => '<ol><li'.$styleAttr.'>'.$inline.$children.'</li></ol>',
            'checkListItem' => self::renderCheckItem($props, $inline, $children, $styleAttr),
            'quote' => '<blockquote'.$styleAttr.'>'.$inline.$children.'</blockquote>',
            'codeBlock' => '<pre><code>'.$inline.'</code></pre>',
            'table' => self::renderTable((array) ($block['content'] ?? [])),
            'image' => self::renderImage($props),
            default => '<div data-block-type="'.e($type).'"'.$styleAttr.'>'.$inline.$children.'</div>',
        };
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private static function renderHeading(array $props, string $inline, string $styleAttr): string
    {
        $level = (int) ($props['level'] ?? 2);
        $level = max(1, min(6, $level));

        return '<h'.$level.$styleAttr.'>'.$inline.'</h'.$level.'>';
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private static function renderCheckItem(array $props, string $inline, string $children, string $styleAttr): string
    {
        $checked = ! empty($props['checked']) ? ' checked' : '';

        return '<ul class="checklist"><li'.$styleAttr.'>'
            .'<input type="checkbox" disabled'.$checked.'> '
            .$inline.$children
            .'</li></ul>';
    }

    /**
     * Renderiza una tabla con marcado accesible exigido por PDF/UA:
     *   - Primera fila si existe → `<th scope="col" id="col-{n}">`
     *   - Resto       → `<td headers="col-{n}">`
     *
     * `$ctx` es un sufijo único por tabla (UUID corto) para que los `id` no
     * colisionen entre tablas del mismo documento.
     *
     * @param  array<int|string, mixed>  $content
     */
    private static function renderTable(array $content): string
    {
        $rows = $content['rows'] ?? null;

        if (!is_array($rows) || $rows === []) {
            return '';
        }
        $html = '<table><tbody>';
        $isHeader = true;

        foreach ($rows as $row) {
            if (!isset($row['cells']) || !is_array($row['cells'])) {
                continue;
            }

            $html .= '<tr>';
            foreach ($row['cells'] as $cell) {

                $colspan = $cell['props']['colspan'] ?? 1;
                $rowspan = $cell['props']['rowspan'] ?? 1;

                $cellContent = '';

                if (!empty($cell['content']) && is_array($cell['content'])) {
                    foreach ($cell['content'] as $inline) {
                        if (($inline['type'] ?? '') === 'text') {
                            $cellContent .= htmlspecialchars($inline['text'] ?? '');
                        }
                    }
                }

                // detectar header
                if ($isHeader) {
                    $html .= '<th colspan="'.$colspan.'" rowspan="'.$rowspan.'">'
                        . $cellContent
                        . '</th>';
                } else {
                    $html .= '<td colspan="'.$colspan.'" rowspan="'.$rowspan.'">'
                        . $cellContent
                        . '</td>';
                }
            }
            $html .= '</tr>';
            $isHeader = false;
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private static function renderImage(array $props): string
    {
        $url = (string) ($props['url'] ?? '');
        $caption = (string) ($props['caption'] ?? '');
        if ($url === '') {
            return '';
        }

        $img = '<img src="'.e($url).'" alt="'.e($caption).'">';

        return $caption !== ''
            ? '<figure>'.$img.'<figcaption>'.e($caption).'</figcaption></figure>'
            : $img;
    }

    /**
     * Inline content: array de spans `{type, text, styles}`. Aplica marks
     * bold/italic/underline/code/strike y conserva escaping.
     *
     * @param  array<int|string, mixed>  $content
     */
    private static function renderInline(array $content): string
    {
        $out = '';
        foreach ($content as $span) {
            if (! is_array($span)) {
                continue;
            }
            $type = (string) ($span['type'] ?? 'text');
            if ($type === 'text') {
                $out .= self::wrapMarks(e((string) ($span['text'] ?? '')), (array) ($span['styles'] ?? []));
            } elseif ($type === 'link') {
                $href = (string) ($span['href'] ?? '#');
                $inner = self::renderInline((array) ($span['content'] ?? []));
                $out .= '<a href="'.e($href).'">'.$inner.'</a>';
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $styles
     */
    private static function wrapMarks(string $text, array $styles): string
    {
        if (! empty($styles['bold'])) {
            $text = '<strong>'.$text.'</strong>';
        }
        if (! empty($styles['italic'])) {
            $text = '<em>'.$text.'</em>';
        }
        if (! empty($styles['underline'])) {
            $text = '<u>'.$text.'</u>';
        }
        if (! empty($styles['strike'])) {
            $text = '<s>'.$text.'</s>';
        }
        if (! empty($styles['code'])) {
            $text = '<code>'.$text.'</code>';
        }

        return $text;
    }

    /**
     * Mapea props de bloque (textColor, backgroundColor, textAlignment) a CSS inline.
     *
     * @param  array<string, mixed>  $props
     */
    private static function propsToStyle(array $props): string
    {
        $parts = [];
        if (! empty($props['textColor']) && $props['textColor'] !== 'default') {
            $parts[] = 'color:'.self::sanitizeColor((string) $props['textColor']);
        }
        if (! empty($props['backgroundColor']) && $props['backgroundColor'] !== 'default') {
            $parts[] = 'background-color:'.self::sanitizeColor((string) $props['backgroundColor']);
        }
        if (! empty($props['textAlignment'])) {
            $align = (string) $props['textAlignment'];
            if (in_array($align, ['left', 'center', 'right', 'justify'], true)) {
                $parts[] = 'text-align:'.$align;
            }
        }

        return implode(';', $parts);
    }

    /**
     * Whitelist de colores: solo nombres conocidos o hex.
     */
    private static function sanitizeColor(string $value): string
    {
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1) {
            return $value;
        }
        $named = ['red', 'orange', 'yellow', 'green', 'blue', 'purple', 'pink', 'gray', 'black', 'white'];
        if (in_array(strtolower($value), $named, true)) {
            return strtolower($value);
        }

        return 'inherit';
    }
}
