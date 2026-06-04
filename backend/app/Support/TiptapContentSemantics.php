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
        $nodes = self::toContentArray($content);
        while ($nodes !== [] && self::isEmptyBlockNode($nodes[array_key_last($nodes)])) {
            array_pop($nodes);
        }

        return $nodes;
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

        return self::inlineTextLength($node['content'] ?? []) === 0;
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
