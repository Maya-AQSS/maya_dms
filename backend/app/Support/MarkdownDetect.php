<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Heuristic mirror of the JS `looksLikeMarkdown` (shared-editor-react): does
 * plain text contain Markdown worth converting to nodes? Conservative so plain
 * prose is never rewritten.
 */
final class MarkdownDetect
{
    private const BLOCK = [
        '/^#{1,6}\s+\S/m',      // ATX heading
        '/^\s*[-*+]\s+\S/m',    // unordered list
        '/^\s*\d+\.\s+\S/m',    // ordered list
        '/^\s*>\s+\S/m',        // blockquote
        '/^```/m',              // fenced code
        // GFM table: require a delimiter row (|---|), so intentional pipe text
        // like "| Total | 30 |" (no delimiter) is NOT flagged.
        '/\|\s*:?-{3,}|-{3,}:?\s*\|/',
    ];

    private const INLINE = [
        '/\*\*[^\s*][^*]*\*\*/', // **bold**
        '/__[^\s_][^_]*__/',     // __bold__
        '/~~[^\s~][^~]*~~/',     // ~~strike~~
        '/`[^`\n]+`/',           // `code`
        '/\[[^\]]+\]\([^)\s]+\)/', // [text](url)
    ];

    public static function looksLikeMarkdown(string $text): bool
    {
        if (strlen($text) < 2) {
            return false;
        }
        foreach (self::BLOCK as $re) {
            if (preg_match($re, $text) === 1) {
                return true;
            }
        }
        foreach (self::INLINE as $re) {
            if (preg_match($re, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
