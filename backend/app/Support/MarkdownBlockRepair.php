<?php

declare(strict_types=1);

namespace App\Support;

use Maya\Editor\Converters\MarkdownToTiptap;

/**
 * Repairs stored TipTap block content where Markdown was persisted as literal
 * plain text (the cause of "## " / "**bold**" showing verbatim in previews).
 *
 * Conservative by design: only converts paragraph/heading nodes whose inline
 * content is ENTIRELY unstyled text (no existing marks) AND looks like Markdown,
 * so already-formatted content is never clobbered. Code blocks are left alone
 * unless explicitly opted in (their `## `/backticks may be intentional code).
 *
 * Pure transformation — no persistence, no events. Idempotent: re-running on
 * already-clean content is a no-op.
 */
final class MarkdownBlockRepair
{
    /**
     * @param  list<array<string, mixed>>  $content  TipTap content array (block nodes)
     * @return array{content: list<array<string, mixed>>, changed: bool}
     */
    public static function repair(array $content, bool $includeCodeBlocks = false): array
    {
        $changed = false;
        $out = [];
        foreach ($content as $node) {
            if (! is_array($node)) {
                $out[] = $node;

                continue;
            }
            [$nodes, $nodeChanged] = self::repairNode($node, $includeCodeBlocks);
            $changed = $changed || $nodeChanged;
            foreach ($nodes as $n) {
                $out[] = $n;
            }
        }

        return ['content' => $out, 'changed' => $changed];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private static function repairNode(array $node, bool $includeCodeBlocks): array
    {
        $type = (string) ($node['type'] ?? '');

        if (in_array($type, ['paragraph', 'heading'], true)) {
            return self::repairTextBlock($node, $type);
        }

        if ($type === 'codeBlock' && $includeCodeBlocks) {
            return self::repairCodeBlock($node);
        }

        // Recurse into block containers (lists, quotes, tables) so nested
        // literal Markdown is repaired too.
        if (self::isContainer($type) && isset($node['content']) && is_array($node['content'])) {
            $result = self::repair($node['content'], $includeCodeBlocks);
            $node['content'] = $result['content'];

            return [[$node], $result['changed']];
        }

        return [[$node], false];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private static function repairTextBlock(array $node, string $type): array
    {
        $children = is_array($node['content'] ?? null) ? $node['content'] : [];
        if ($children === []) {
            return [[$node], false];
        }

        // Case A — a paragraph that is entirely unstyled text carrying BLOCK-level
        // Markdown (heading, list, quote, fence, table) is replaced by the parsed
        // block nodes. Headings never become blocks (they stay headings → Case B).
        if ($type === 'paragraph' && self::isPlainUnstyledText($children)) {
            $text = self::concatText($children);
            if (self::isBlockMarkdown($text)) {
                $converted = MarkdownToTiptap::convert($text);
                if ($converted !== []) {
                    if (count($converted) === 1 && ($converted[0]['type'] ?? '') === 'paragraph') {
                        $node['content'] = $converted[0]['content'] ?? [];

                        return [[$node], true];
                    }

                    return [$converted, true];
                }
            }
        }

        // Case B — fix inline Markdown per text node, preserving (merging) any
        // marks the node already carries. Handles e.g. a fully-bold paragraph
        // whose text still contains literal `**NOMBRE**`.
        $changed = false;
        $newChildren = [];
        foreach ($children as $child) {
            if (
                is_array($child)
                && ($child['type'] ?? '') === 'text'
                && MarkdownDetect::looksLikeMarkdown((string) ($child['text'] ?? ''))
            ) {
                $parsed = self::parseInline((string) $child['text']);
                if ($parsed !== []) {
                    $existing = is_array($child['marks'] ?? null) ? $child['marks'] : [];
                    foreach ($parsed as $piece) {
                        $newChildren[] = self::mergeMarks($piece, $existing);
                    }
                    $changed = true;

                    continue;
                }
            }
            $newChildren[] = $child;
        }

        if ($changed) {
            $node['content'] = $newChildren;

            return [[$node], true];
        }

        return [[$node], false];
    }

    private static function isBlockMarkdown(string $text): bool
    {
        if (preg_match('/^#{1,6}\s|^\s*[-*+]\s|^\s*\d+\.\s|^\s*>\s|^```/m', $text) === 1) {
            return true;
        }

        // GFM table only if a delimiter row is present (avoid intentional pipe text).
        return preg_match('/\|\s*:?-{3,}|-{3,}:?\s*\|/', $text) === 1;
    }

    /**
     * Parse a string of inline Markdown into TipTap inline nodes (text/hardBreak
     * with marks), dropping any block wrappers.
     *
     * @return list<array<string, mixed>>
     */
    private static function parseInline(string $text): array
    {
        return self::flattenInline(MarkdownToTiptap::convert($text));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<array<string, mixed>>  $existing
     * @return array<string, mixed>
     */
    private static function mergeMarks(array $node, array $existing): array
    {
        if ($existing === []) {
            return $node;
        }
        $marks = is_array($node['marks'] ?? null) ? $node['marks'] : [];
        foreach ($existing as $mark) {
            if (! is_array($mark)) {
                continue;
            }
            $type = (string) ($mark['type'] ?? '');
            $present = false;
            foreach ($marks as $m) {
                if (is_array($m) && (string) ($m['type'] ?? '') === $type) {
                    $present = true;
                    break;
                }
            }
            if (! $present) {
                $marks[] = $mark;
            }
        }
        if ($marks !== []) {
            $node['marks'] = array_values($marks);
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private static function repairCodeBlock(array $node): array
    {
        $children = is_array($node['content'] ?? null) ? $node['content'] : [];
        $text = self::concatText($children);
        // Only treat as mis-stored prose if it carries block-level Markdown.
        if (! preg_match('/^#{1,6}\s|\*\*[^*]+\*\*|^\s*[-*+]\s|^\s*\d+\.\s/m', $text)) {
            return [[$node], false];
        }

        $converted = MarkdownToTiptap::convert($text);

        return $converted === [] ? [[$node], false] : [$converted, true];
    }

    /**
     * True when every inline child is an unstyled text node (no marks). Mixed or
     * already-formatted content is left untouched.
     *
     * @param  list<mixed>  $children
     */
    private static function isPlainUnstyledText(array $children): bool
    {
        if ($children === []) {
            return false;
        }
        foreach ($children as $child) {
            if (! is_array($child) || ($child['type'] ?? '') !== 'text') {
                return false;
            }
            if (! empty($child['marks'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<mixed>  $children
     */
    private static function concatText(array $children): string
    {
        $text = '';
        foreach ($children as $child) {
            if (is_array($child) && ($child['type'] ?? '') === 'text') {
                $text .= (string) ($child['text'] ?? '');
            }
        }

        return $text;
    }

    /**
     * Collect inline content from parsed block nodes (for heading repair).
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private static function flattenInline(array $blocks): array
    {
        $inline = [];
        foreach ($blocks as $block) {
            foreach (($block['content'] ?? []) as $child) {
                if (is_array($child) && in_array(($child['type'] ?? ''), ['text', 'hardBreak'], true)) {
                    $inline[] = $child;
                }
            }
        }

        return $inline;
    }

    private static function isContainer(string $type): bool
    {
        return in_array($type, [
            'bulletList', 'orderedList', 'listItem', 'taskList', 'taskItem',
            'blockquote', 'table', 'tableRow', 'tableCell', 'tableHeader',
        ], true);
    }
}
