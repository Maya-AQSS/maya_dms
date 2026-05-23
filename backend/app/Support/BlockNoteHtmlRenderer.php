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
     * Renderiza una secuencia de bloques con metadatos de kind. Cada item del
     * input es `['kind' => string, 'content' => array]`. Envuelve cada bloque
     * en `<section class="block-kind-{kind}">` para que el CSS Paged Media de
     * `render.blade.php` aplique saltos de página y supresión de chrome del
     * theme en cover/blank.
     *
     * - kind=content → render BlockNote normal.
     * - kind=cover   → render BlockNote normal dentro de la sección (CSS aplica
     *                  reset y supresión de chrome).
     * - kind=blank   → sección vacía marcada como artifact PDF/UA-1.
     * - kind=toc     → índice generado a partir de los headings de los bloques
     *                  `content` previos en el mismo documento.
     *
     * IDs de heading: en una primera pasada se asigna `props.id` determinístico
     * (`block-{n}-h-{m}`) a cada heading de bloques `content`, y se construye
     * el árbol del índice. En la segunda pasada se rendea el HTML — los
     * headings emiten `<hN id="…">`, el TOC referencia con `target-counter()`.
     *
     * @param  array<int, array{kind?: string, content?: array<int, mixed>}>  $blocksWithKind
     */
    public static function renderDocument(array $blocksWithKind): string
    {
        $blocksWithKind = self::assignHeadingIds($blocksWithKind);
        $tocEntries = self::collectTocEntries($blocksWithKind);

        $buffer = '';
        foreach ($blocksWithKind as $item) {
            if (! is_array($item)) {
                continue;
            }
            $kind = is_string($item['kind'] ?? null) && $item['kind'] !== '' ? $item['kind'] : 'content';
            $content = is_array($item['content'] ?? null) ? $item['content'] : [];

            $buffer .= match ($kind) {
                'cover' => '<section class="block-kind-cover">'.self::renderBlocks($content).'</section>',
                'blank' => '<section class="block-kind-blank" role="presentation" aria-hidden="true"></section>',
                'toc' => '<section class="block-kind-toc">'.self::renderToc($tocEntries).'</section>',
                default => '<section class="block-kind-content">'.self::renderBlocks($content).'</section>',
            };
        }

        return $buffer;
    }

    /**
     * Primera pasada: asigna `props.id` determinístico a cada heading de los
     * bloques `content`. Devuelve el array de bloques con los IDs inyectados.
     * Los bloques no-content se devuelven sin cambios.
     *
     * @param  array<int, array{kind?: string, content?: array<int, mixed>}>  $blocksWithKind
     * @return array<int, array{kind?: string, content?: array<int, mixed>}>
     */
    private static function assignHeadingIds(array $blocksWithKind): array
    {
        $out = [];
        $blockIndex = 0;
        foreach ($blocksWithKind as $item) {
            if (! is_array($item)) {
                $out[] = $item;
                $blockIndex++;
                continue;
            }
            $kind = is_string($item['kind'] ?? null) && $item['kind'] !== '' ? $item['kind'] : 'content';
            if ($kind !== 'content') {
                $out[] = $item;
                $blockIndex++;
                continue;
            }
            $content = is_array($item['content'] ?? null) ? $item['content'] : [];
            $headingCounter = 0;
            $item['content'] = self::injectHeadingIds($content, $blockIndex, $headingCounter);
            $out[] = $item;
            $blockIndex++;
        }

        return $out;
    }

    /**
     * Recorre el árbol BlockNote y asigna `props.id = "block-{$blockIndex}-h-{n}"`
     * a cada heading que no tenga ID propio. Mutación pura (devuelve copia).
     *
     * @param  array<int, mixed>  $tree
     * @return array<int, mixed>
     */
    private static function injectHeadingIds(array $tree, int $blockIndex, int &$headingCounter): array
    {
        $out = [];
        foreach ($tree as $node) {
            if (! is_array($node)) {
                $out[] = $node;
                continue;
            }
            if (($node['type'] ?? null) === 'heading') {
                $props = (array) ($node['props'] ?? []);
                if (! isset($props['id']) || ! is_string($props['id']) || $props['id'] === '') {
                    $props['id'] = sprintf('block-%d-h-%d', $blockIndex, $headingCounter);
                }
                $node['props'] = $props;
                $headingCounter++;
            }
            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = self::injectHeadingIds($node['children'], $blockIndex, $headingCounter);
            }
            $out[] = $node;
        }

        return $out;
    }

    /**
     * Recoge entradas de TOC: cada heading de los bloques `content` produce
     * `['id' => string, 'level' => int, 'text' => string]`.
     *
     * @param  array<int, array{kind?: string, content?: array<int, mixed>}>  $blocksWithKind
     * @return list<array{id: string, level: int, text: string}>
     */
    private static function collectTocEntries(array $blocksWithKind): array
    {
        $entries = [];
        foreach ($blocksWithKind as $item) {
            if (! is_array($item)) {
                continue;
            }
            $kind = is_string($item['kind'] ?? null) && $item['kind'] !== '' ? $item['kind'] : 'content';
            if ($kind !== 'content') {
                continue;
            }
            $content = is_array($item['content'] ?? null) ? $item['content'] : [];
            self::walkHeadings($content, $entries);
        }

        return $entries;
    }

    /**
     * @param  array<int, mixed>  $tree
     * @param  list<array{id: string, level: int, text: string}>  $entries
     */
    private static function walkHeadings(array $tree, array &$entries): void
    {
        foreach ($tree as $node) {
            if (! is_array($node)) {
                continue;
            }
            if (($node['type'] ?? null) === 'heading') {
                $props = (array) ($node['props'] ?? []);
                $id = is_string($props['id'] ?? null) ? $props['id'] : '';
                $level = (int) ($props['level'] ?? 2);
                $level = max(1, min(6, $level));
                $text = self::plainTextFromInline((array) ($node['content'] ?? []));
                if ($id !== '' && $text !== '') {
                    $entries[] = ['id' => $id, 'level' => $level, 'text' => $text];
                }
            }
            if (isset($node['children']) && is_array($node['children'])) {
                self::walkHeadings($node['children'], $entries);
            }
        }
    }

    /**
     * @param  array<int, mixed>  $content
     */
    private static function plainTextFromInline(array $content): string
    {
        $buf = '';
        foreach ($content as $span) {
            if (! is_array($span)) {
                continue;
            }
            $type = (string) ($span['type'] ?? 'text');
            if ($type === 'text') {
                $buf .= (string) ($span['text'] ?? '');
            } elseif ($type === 'link') {
                $buf .= self::plainTextFromInline((array) ($span['content'] ?? []));
            }
        }

        return trim($buf);
    }

    /**
     * Renderiza la `<ol class="toc">` a partir de las entradas recogidas.
     * Cada entrada emite un `<li class="toc-h{level}">` con `<a>` al ancla
     * y `<span class="toc-page" data-href="#…">` para que WeasyPrint pinte
     * el número de página real vía `target-counter()`.
     *
     * @param  list<array{id: string, level: int, text: string}>  $entries
     */
    private static function renderToc(array $entries): string
    {
        if ($entries === []) {
            return '<ol class="toc"></ol>';
        }

        $html = '<ol class="toc">';
        foreach ($entries as $entry) {
            $href = '#'.$entry['id'];
            $html .= '<li class="toc-h'.$entry['level'].'">'
                .'<a href="'.e($href).'">'.e($entry['text']).'</a>'
                .'<span class="toc-page" data-href="'.e($href).'"></span>'
                .'</li>';
        }
        $html .= '</ol>';

        return $html;
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

        $id = is_string($props['id'] ?? null) && $props['id'] !== '' ? $props['id'] : null;
        $idAttr = $id !== null ? ' id="'.e($id).'"' : '';

        return '<h'.$level.$idAttr.$styleAttr.'>'.$inline.'</h'.$level.'>';
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
     *   - Primera fila → `<th scope="col" id="col-{n}">`
     *   - Resto       → `<td headers="col-{n}">`
     *
     * `$ctx` es un sufijo único por tabla (UUID corto) para que los `id` no
     * colisionen entre tablas del mismo documento.
     *
     * @param  array<int|string, mixed>  $content
     */
    private static function renderTable(array $content): string
    {
        // BlockNote table: { type: "tableContent", rows: [{ cells: [[inline]] }] }
        $rows = $content['rows'] ?? null;
        if (! is_array($rows) || $rows === []) {
            return '';
        }

        $ctx = substr(bin2hex(random_bytes(4)), 0, 6);

        $html = '<table>';
        $isHeader = true;
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['cells'])) {
                continue;
            }

            if ($isHeader) {
                $html .= '<thead><tr>';
                $colIdx = 0;
                foreach ((array) $row['cells'] as $cell) {
                    $colId = sprintf('col-%s-%d', $ctx, $colIdx);
                    $html .= '<th id="'.$colId.'" scope="col">'
                        .self::renderInline((array) $cell)
                        .'</th>';
                    $colIdx++;
                }
                $html .= '</tr></thead><tbody>';
                $isHeader = false;

                continue;
            }

            $html .= '<tr>';
            $colIdx = 0;
            foreach ((array) $row['cells'] as $cell) {
                $colId = sprintf('col-%s-%d', $ctx, $colIdx);
                $html .= '<td headers="'.$colId.'">'
                    .self::renderInline((array) $cell)
                    .'</td>';
                $colIdx++;
            }
            $html .= '</tr>';
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
