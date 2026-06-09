<?php

declare(strict_types=1);

namespace App\Support;

/**
 * La descripción de un bloque de plantilla es texto plano.
 * Este normalizador convierte valores legados (JSON BlockNote, arrays doc/paragraph) a string útil para API y UI.
 */
final class TemplateBlockDescriptionNormalizer
{
    /**
     * Normaliza cualquier valor de descripción (prosa plana, JSON legado,
     * BlockNote, doc Tiptap) a un documento Tiptap `{type:doc, content:[...]}`
     * — la forma que esperan el cast `array` del modelo y el frontend
     * (`normalizeBlockContentForEditor`). Un doc Tiptap limpio se preserva tal
     * cual; la prosa y las formas legadas se aplanan a párrafos.
     *
     * @return array{type: string, content: list<array<string, mixed>>}|null
     */
    public static function toTiptapDoc(mixed $value): ?array
    {
        if (is_string($value)) {
            $t = trim($value);
            if ($t === '') {
                return null;
            }

            if ($t[0] === '{' || $t[0] === '[') {
                $decoded = json_decode($t, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return self::toTiptapDoc($decoded);
                }
            }

            return self::wrapProse($t);
        }

        if (is_array($value)) {
            // Doc Tiptap limpio (sin marcadores BlockNote) → se preserva el rich text.
            if (($value['type'] ?? null) === 'doc'
                && is_array($value['content'] ?? null)
                && ! self::looksLikeBlockNote($value)) {
                return ['type' => 'doc', 'content' => array_values($value['content'])];
            }

            // BlockNote / lista / prosa estructurada → aplanar a texto y reenvolver.
            $text = self::toPlainString($value);

            return $text === null ? null : self::wrapProse($text);
        }

        return null;
    }

    /** Detecta formas BlockNote (usan `props`/`styles`/`children`, no `attrs`/`marks`). */
    private static function looksLikeBlockNote(array $doc): bool
    {
        $json = json_encode($doc);

        return $json !== false
            && (str_contains($json, '"props"') || str_contains($json, '"styles"') || str_contains($json, '"children"'));
    }

    /**
     * Envuelve prosa plana en un doc Tiptap: cada bloque separado por línea en
     * blanco se convierte en un párrafo.
     *
     * @return array{type: string, content: list<array<string, mixed>>}
     */
    private static function wrapProse(string $text): array
    {
        $paragraphs = preg_split('/\n{2,}/', trim($text)) ?: [];
        $content = [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            $content[] = [
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'left'],
                'content' => [['type' => 'text', 'text' => $paragraph]],
            ];
        }

        return ['type' => 'doc', 'content' => $content];
    }

    public static function toPlainString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $t = trim($value);
            if ($t === '') {
                return null;
            }

            if ($t[0] === '{' || $t[0] === '[') {
                $decoded = json_decode($t, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $extracted = self::extractFromLegacyStructure($decoded);
                    if ($extracted !== '') {
                        return $extracted;
                    }
                }
            }

            return $t;
        }

        if (is_array($value)) {
            $extracted = self::extractFromLegacyStructure($value);

            return $extracted === '' ? null : $extracted;
        }

        return null;
    }

    /**
     * @param  array<mixed>|mixed  $node
     */
    private static function extractFromLegacyStructure(mixed $node): string
    {
        if (! is_array($node)) {
            return '';
        }

        $type = $node['type'] ?? null;

        if ($type === 'doc' && isset($node['content']) && is_array($node['content'])) {
            return self::collapseBlocks($node['content']);
        }

        if ($type === 'bulletListItem' || $type === 'numberedListItem') {
            $inline = self::extractInlineText($node['content'] ?? []);
            $children = isset($node['children']) && is_array($node['children'])
                ? self::collapseBlocks($node['children'])
                : '';

            return trim($inline.($children !== '' ? "\n\n".$children : ''));
        }

        if (in_array($type, ['paragraph', 'heading', 'blockquote'], true)) {
            return self::extractInlineText($node['content'] ?? []);
        }

        if (array_is_list($node)) {
            return self::collapseBlocks($node);
        }

        $parts = [];
        if (isset($node['content']) && is_array($node['content'])) {
            $parts[] = self::collapseBlocks($node['content']);
        }
        if (isset($node['children']) && is_array($node['children']) && $node['children'] !== []) {
            $parts[] = self::collapseBlocks($node['children']);
        }

        return trim(implode("\n\n", array_filter($parts, static fn (string $p): bool => $p !== '')));
    }

    /**
     * @param  list<mixed>  $blocks
     */
    private static function collapseBlocks(array $blocks): string
    {
        $out = [];
        foreach ($blocks as $b) {
            $t = self::extractFromLegacyStructure($b);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return implode("\n\n", $out);
    }

    /**
     * @param  list<mixed>|mixed  $content
     */
    private static function extractInlineText(mixed $content): string
    {
        if (! is_array($content)) {
            return '';
        }

        $parts = [];
        foreach ($content as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['type'] ?? '') === 'text') {
                $parts[] = (string) ($item['text'] ?? '');
            } else {
                $parts[] = self::extractFromLegacyStructure($item);
            }
        }

        return trim(implode('', $parts));
    }
}
