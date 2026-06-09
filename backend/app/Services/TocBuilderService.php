<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Construye el índice (tabla de contenidos) de un documento dentro de los
 * bloques de tipo `index`.
 *
 * Modelo híbrido (decidido con el usuario):
 *   - Entrada por BLOQUE: el bloque índice referencia otros bloques por id
 *     (config en su `default_content`: `{ kind:'index', blockIds:[], includeHeadings:bool }`).
 *     Si no hay `blockIds`, por defecto entran todos los bloques de contenido.
 *   - Subentradas por ENCABEZADO (opcional, `includeHeadings`): los H1–H3 del
 *     contenido de cada bloque seleccionado, indentados.
 *
 * Cada sección de bloque lleva `id="block-{id}"` (lo emite el render service);
 * el índice enlaza a ese ancla. El número de página NO se calcula aquí: lo
 * resuelve CSS `target-counter(attr(href), page)` (WeasyPrint) o el JS de
 * paged.js en el preview (ver `documents/render.blade.php`).
 *
 * No toca el paquete compartido `shared-editor-laravel`: opera sobre el HTML ya
 * renderizado, manteniendo la maquetación dentro de maya_dms.
 */
class TocBuilderService
{
    /** Niveles de encabezado que entran como subentradas. */
    private const HEADING_TAGS = ['h1', 'h2', 'h3'];

    /**
     * Cota de tamaño del HTML a parsear (defensa DoS): por encima de este límite
     * se omite la construcción del índice y se devuelve el HTML intacto. Un
     * documento real cabe holgadamente en 4 MiB de HTML renderizado.
     */
    private const MAX_HTML_BYTES = 4 * 1024 * 1024;

    /** Tope de subentradas de encabezado por índice (defensa DoS). */
    private const MAX_HEADING_ENTRIES = 1000;

    /**
     * @param  string  $bodyHtml  HTML ensamblado de las secciones de bloque
     * @param  list<array{id: string, title: string, block_type: string, index_config?: array<string,mixed>|null}>  $blocks
     *                                                                                                                       Metadata ordenada de los bloques (id = id del bloque de plantilla, espejo del ancla `block-{id}`).
     */
    public function build(string $bodyHtml, array $blocks): string
    {
        $hasIndex = false;
        foreach ($blocks as $b) {
            if (($b['block_type'] ?? '') === 'index') {
                $hasIndex = true;
                break;
            }
        }
        if (! $hasIndex || trim($bodyHtml) === '') {
            return $bodyHtml;
        }

        // Defensa DoS: no parseamos HTML patológicamente grande.
        if (strlen($bodyHtml) > self::MAX_HTML_BYTES) {
            return $bodyHtml;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8"?><div id="__toc_root__">'.$bodyHtml.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $bodyHtml;
        }

        $xpath = new DOMXPath($dom);
        $usedIds = $this->existingIds($xpath);
        $counter = 0;
        $headingEntries = 0;

        // Mapa id-de-bloque → sección DOM (por el ancla `id="block-{id}"`).
        $sectionById = [];
        foreach ($xpath->query("//section[contains(concat(' ', normalize-space(@class), ' '), ' doc-block ')]") ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $domId = $node->getAttribute('id');
                if (str_starts_with($domId, 'block-')) {
                    $sectionById[substr($domId, 6)] = $node;
                }
            }
        }

        // IDs de bloque de contenido por defecto (cuando el índice no fija blockIds).
        $defaultBlockIds = [];
        foreach ($blocks as $b) {
            if (($b['block_type'] ?? '') === 'content') {
                $defaultBlockIds[] = (string) $b['id'];
            }
        }
        $titleById = [];
        $orderIndex = [];
        foreach ($blocks as $i => $b) {
            $titleById[(string) $b['id']] = (string) ($b['title'] ?? '');
            $orderIndex[(string) $b['id']] = $i;
        }

        $built = false;
        foreach ($blocks as $b) {
            if (($b['block_type'] ?? '') !== 'index') {
                continue;
            }
            $indexId = (string) $b['id'];
            $section = $sectionById[$indexId] ?? null;
            if (! $section instanceof DOMElement) {
                continue;
            }

            $config = is_array($b['index_config'] ?? null) ? $b['index_config'] : [];
            // blockIds vacío o ausente ⇒ por defecto todos los bloques de contenido.
            $configured = (isset($config['blockIds']) && is_array($config['blockIds']))
                ? array_map('strval', $config['blockIds'])
                : [];
            $selected = $configured !== [] ? $configured : $defaultBlockIds;
            $includeHeadings = (bool) ($config['includeHeadings'] ?? false);

            // Respeta el orden del documento y descarta ids desconocidos / el propio índice.
            $selected = array_values(array_filter(
                array_unique($selected),
                fn (string $id) => $id !== $indexId && isset($orderIndex[$id]),
            ));
            usort($selected, fn (string $a, string $c) => $orderIndex[$a] <=> $orderIndex[$c]);

            if ($selected === []) {
                continue;
            }

            $entries = [];
            foreach ($selected as $blockId) {
                $entries[] = [
                    'level' => 0,
                    'text' => $titleById[$blockId] !== '' ? $titleById[$blockId] : '—',
                    'href' => '#block-'.$blockId,
                ];
                if ($includeHeadings && isset($sectionById[$blockId])) {
                    foreach ($this->headingsIn($sectionById[$blockId]) as $heading) {
                        if ($headingEntries >= self::MAX_HEADING_ENTRIES) {
                            break;
                        }
                        $text = trim((string) $heading->textContent);
                        if ($text === '') {
                            continue;
                        }
                        $headingEntries++;
                        $hid = $heading->getAttribute('id');
                        if ($hid === '') {
                            do {
                                $counter++;
                                $hid = 'doc-toc-'.$counter;
                            } while (isset($usedIds[$hid]));
                            $heading->setAttribute('id', $hid);
                            $usedIds[$hid] = true;
                        }
                        $entries[] = [
                            'level' => (int) substr($heading->nodeName, 1),
                            'text' => $text,
                            'href' => '#'.$hid,
                        ];
                    }
                }
            }

            $section->appendChild($this->buildNav($dom, $entries));
            $built = true;
        }

        if (! $built) {
            return $bodyHtml;
        }

        return $this->innerHtml($dom, $xpath);
    }

    /**
     * @return array<string, true>
     */
    private function existingIds(DOMXPath $xpath): array
    {
        $ids = [];
        foreach ($xpath->query('//*[@id]') ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $id = $node->getAttribute('id');
                if ($id !== '') {
                    $ids[$id] = true;
                }
            }
        }

        return $ids;
    }

    /**
     * @return list<DOMElement>
     */
    private function headingsIn(DOMElement $section): array
    {
        $out = [];
        $this->collectHeadings($section, $out);

        return $out;
    }

    /**
     * @param  list<DOMElement>  $out
     */
    private function collectHeadings(DOMNode $node, array &$out): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if (in_array(strtolower($child->nodeName), self::HEADING_TAGS, true)) {
                    $out[] = $child;
                }
                $this->collectHeadings($child, $out);
            }
        }
    }

    /**
     * @param  list<array{level: int, text: string, href: string}>  $entries
     */
    private function buildNav(DOMDocument $dom, array $entries): DOMElement
    {
        $nav = $dom->createElement('nav');
        $nav->setAttribute('class', 'doc-toc');
        $nav->setAttribute('role', 'doc-toc');

        $list = $dom->createElement('ul');
        $list->setAttribute('class', 'doc-toc__list');

        foreach ($entries as $entry) {
            $item = $dom->createElement('li');
            $cls = $entry['level'] === 0 ? 'doc-toc__item doc-toc__item--block' : 'doc-toc__item doc-toc__item--h'.$entry['level'];
            $item->setAttribute('class', $cls);

            $link = $dom->createElement('a');
            $link->setAttribute('class', 'doc-toc__link');
            $link->setAttribute('href', $entry['href']);

            $textSpan = $dom->createElement('span');
            $textSpan->setAttribute('class', 'doc-toc__text');
            $textSpan->appendChild($dom->createTextNode($entry['text']));
            $link->appendChild($textSpan);

            $leader = $dom->createElement('span');
            $leader->setAttribute('class', 'doc-toc__leader');
            $link->appendChild($leader);

            $pageSpan = $dom->createElement('span');
            $pageSpan->setAttribute('class', 'doc-toc__page');
            $pageSpan->setAttribute('data-target', $entry['href']);
            $link->appendChild($pageSpan);

            $item->appendChild($link);
            $list->appendChild($item);
        }

        $nav->appendChild($list);

        return $nav;
    }

    private function innerHtml(DOMDocument $dom, DOMXPath $xpath): string
    {
        $root = $xpath->query("//div[@id='__toc_root__']")?->item(0);
        if (! $root instanceof DOMElement) {
            return $dom->saveHTML() ?: '';
        }

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }
}
