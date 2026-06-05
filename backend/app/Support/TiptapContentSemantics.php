<?php

namespace App\Support;

/**
 * Semantic empty/filled checks for TipTap JSON stored on document/template blocks.
 * Ignores trailing empty paragraphs ProseMirror adds for cursor placement.
 */
final class TiptapContentSemantics
{
    private const MEANINGFUL_BLOCK_TYPES = [
        'image',
        'table',
        'iframeBlock',
        'alertBlock',
        'horizontalRule',
        'codeBlock',
    ];

    public static function isContentFilled(mixed $content): bool
    {
        if ($content === null) {
            return false;
        }

        if (is_string($content)) {
            return self::htmlVisibleTextLength($content) > 0;
        }

        if (! is_array($content)) {
            return true;
        }

        $nodes = self::normalizeContentArray($content);

        if ($nodes === []) {
            return false;
        }

        foreach ($nodes as $node) {
            if (! self::isEmptyBlockNode($node)) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeContentArray(mixed $content): array
    {
        $nodes = [];
        foreach (self::toContentArray($content) as $node) {
            $canonical = self::canonicalizeNode($node);
            if ($canonical !== null) {
                $nodes[] = $canonical;
            }
        }

        return self::stripTrailingEmptyBlocks($nodes);
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @return array<int, mixed>
     */
    private static function stripTrailingEmptyBlocks(array $nodes): array
    {
        while ($nodes !== [] && self::isEmptyBlockNode($nodes[array_key_last($nodes)])) {
            array_pop($nodes);
        }

        return $nodes;
    }

    /**
     * Elimina atributos volátiles y nodos vacíos fantasma en cualquier profundidad.
     *
     * @return array<string, mixed>|null
     */
    private static function canonicalizeNode(mixed $node): ?array
    {
        if (! is_array($node)) {
            return null;
        }

        $rawType = (string) ($node['type'] ?? '');
        if ($rawType === '') {
            return null;
        }

        if ($rawType === 'text') {
            $text = is_string($node['text'] ?? null) ? $node['text'] : '';
            $marks = $node['marks'] ?? null;
            $hasMarks = is_array($marks) && $marks !== [];
            if (trim(str_replace("\u{00A0}", ' ', $text)) === '' && ! $hasMarks) {
                return null;
            }
            $out = ['type' => 'text'];
            if (is_string($node['text'] ?? null)) {
                $out['text'] = $node['text'];
            }
            if ($hasMarks) {
                $out['marks'] = $marks;
            }

            return $out;
        }

        $type = $rawType === 'tableHeader' ? 'tableCell' : $rawType;
        $out = ['type' => $type];

        if (isset($node['attrs']) && is_array($node['attrs'])) {
            $attrs = $node['attrs'];
            if ($type === 'image') {
                $picked = [];
                foreach (['src', 'alt'] as $key) {
                    $value = $attrs[$key] ?? null;
                    if (is_string($value) && trim($value) !== '') {
                        $picked[$key] = $value;
                    }
                }
                if ($picked !== []) {
                    $out['attrs'] = $picked;
                }
            } else {
                foreach (['colwidth', 'columnSizing', 'data-colwidth', 'width', 'height', 'style', 'class', 'title'] as $key) {
                    unset($attrs[$key]);
                }
                if ($type === 'tableCell') {
                    if (($attrs['colspan'] ?? null) === 1) {
                        unset($attrs['colspan']);
                    }
                    if (($attrs['rowspan'] ?? null) === 1) {
                        unset($attrs['rowspan']);
                    }
                }
                if ($attrs !== []) {
                    $out['attrs'] = $attrs;
                }
            }
        }

        if (isset($node['content']) && is_array($node['content'])) {
            $children = [];
            foreach ($node['content'] as $child) {
                $canonicalChild = self::canonicalizeNode($child);
                if ($canonicalChild !== null) {
                    $children[] = $canonicalChild;
                }
            }

            if (in_array($rawType, ['bulletList', 'orderedList', 'taskList'], true)) {
                $children = array_values(array_filter(
                    $children,
                    static fn ($child): bool => ! self::isEmptyBlockNode($child),
                ));
            }

            $children = self::stripTrailingEmptyBlocks($children);
            if ($children !== []) {
                $out['content'] = $children;
            }
        }

        return self::isEmptyBlockNode($out) ? null : $out;
    }

    public static function contentEquals(mixed $a, mixed $b): bool
    {
        return json_encode(self::normalizeContentArray($a), \JSON_THROW_ON_ERROR)
            === json_encode(self::normalizeContentArray($b), \JSON_THROW_ON_ERROR);
    }

    private static function toContentArray(mixed $content): array
    {
        if (! is_array($content)) {
            return [];
        }

        if (isset($content['type']) && $content['type'] === 'doc' && is_array($content['content'] ?? null)) {
            return array_values($content['content']);
        }

        return array_is_list($content) ? array_values($content) : [];
    }

    private static function isEmptyBlockNode(mixed $node): bool
    {
        if (! is_array($node)) {
            return true;
        }

        $type = $node['type'] ?? null;
        if (! is_string($type) || $type === '') {
            return true;
        }

        if (in_array($type, self::MEANINGFUL_BLOCK_TYPES, true)) {
            if ($type === 'horizontalRule') {
                return false;
            }
            if ($type === 'image') {
                $src = $node['attrs']['src'] ?? '';

                return ! is_string($src) || trim($src) === '';
            }
            if ($type === 'codeBlock') {
                return self::inlineTextLength($node['content'] ?? []) === 0;
            }

            return false;
        }

        if (in_array($type, ['bulletList', 'orderedList', 'taskList'], true)) {
            $items = is_array($node['content'] ?? null) ? $node['content'] : [];

            return $items === [] || self::everyBlockEmpty($items);
        }

        if (in_array($type, ['listItem', 'taskItem', 'blockquote'], true)) {
            $inner = is_array($node['content'] ?? null) ? $node['content'] : [];

            return $inner === [] || self::everyBlockEmpty($inner);
        }

        if ($type === 'text') {
            $text = is_string($node['text'] ?? null) ? $node['text'] : '';
            $marks = $node['marks'] ?? null;
            $hasMarks = is_array($marks) && $marks !== [];

            return trim(str_replace("\u{00A0}", ' ', $text)) === '' && ! $hasMarks;
        }

        if ($type === 'hardBreak') {
            return true;
        }

        return ! self::blockChildrenHaveMeaningfulContent($node['content'] ?? []);
    }

    /**
     * @param  array<int, mixed>  $nodes
     */
    private static function blockChildrenHaveMeaningfulContent(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $type = $node['type'] ?? '';
            if ($type === 'text' && is_string($node['text'] ?? null)) {
                if (trim(str_replace("\u{00A0}", ' ', $node['text'])) !== '') {
                    return true;
                }
                continue;
            }
            if ($type === 'hardBreak') {
                continue;
            }
            if (in_array($type, self::MEANINGFUL_BLOCK_TYPES, true) && ! self::isEmptyBlockNode($node)) {
                return true;
            }
            $inner = $node['content'] ?? null;
            if (is_array($inner) && self::blockChildrenHaveMeaningfulContent($inner)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $nodes
     */
    private static function everyBlockEmpty(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (! self::isEmptyBlockNode($node)) {
                return false;
            }
        }

        return true;
    }

    private static function inlineTextLength(mixed $nodes): int
    {
        if (! is_array($nodes)) {
            return 0;
        }

        $length = 0;
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $type = $node['type'] ?? '';
            if ($type === 'text' && is_string($node['text'] ?? null)) {
                $length += strlen(trim(str_replace("\u{00A0}", ' ', $node['text'])));
            } elseif ($type === 'hardBreak') {
                continue;
            } elseif (isset($node['content'])) {
                $length += self::inlineTextLength($node['content']);
            }
        }

        return $length;
    }

    private static function htmlVisibleTextLength(string $html): int
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return strlen(trim(str_replace("\u{00A0}", ' ', $text)));
    }
}
