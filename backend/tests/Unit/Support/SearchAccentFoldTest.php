<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SearchAccentFold;
use PHPUnit\Framework\TestCase;

final class SearchAccentFoldTest extends TestCase
{
    // ─── fold ─────────────────────────────────────────────────────────────────

    public function test_fold_lowercases_input(): void
    {
        $this->assertSame('hello', SearchAccentFold::fold('HELLO'));
    }

    public function test_fold_trims_whitespace(): void
    {
        $this->assertSame('hello', SearchAccentFold::fold('  hello  '));
    }

    public function test_fold_replaces_accented_vowels(): void
    {
        $this->assertSame('aeiou', SearchAccentFold::fold('áéíóú'));
    }

    public function test_fold_replaces_uppercase_accented_vowels(): void
    {
        $this->assertSame('aeiou', SearchAccentFold::fold('ÁÉÍÓÚ'));
    }

    public function test_fold_replaces_additional_accent_variants(): void
    {
        $this->assertSame('aeiou', SearchAccentFold::fold('àèìòù'));
    }

    public function test_fold_replaces_n_with_tilde(): void
    {
        $this->assertSame('n', SearchAccentFold::fold('ñ'));
    }

    public function test_fold_replaces_c_cedilla(): void
    {
        $this->assertSame('c', SearchAccentFold::fold('ç'));
    }

    public function test_fold_replaces_german_sharp_s_with_ss(): void
    {
        $this->assertSame('ss', SearchAccentFold::fold('ß'));
    }

    public function test_fold_replaces_oe_ligature(): void
    {
        $this->assertSame('oe', SearchAccentFold::fold('œ'));
    }

    public function test_fold_replaces_ae_ligature(): void
    {
        $this->assertSame('ae', SearchAccentFold::fold('æ'));
    }

    public function test_fold_leaves_plain_ascii_unchanged(): void
    {
        $this->assertSame('hello world', SearchAccentFold::fold('hello world'));
    }

    public function test_fold_empty_string(): void
    {
        $this->assertSame('', SearchAccentFold::fold(''));
    }

    public function test_fold_mixed_input(): void
    {
        // 'Ángel' → 'angel'
        $this->assertSame('angel', SearchAccentFold::fold('Ángel'));
    }

    public function test_fold_complex_spanish_word(): void
    {
        // 'Instrucción' → 'instruccion'
        $this->assertSame('instruccion', SearchAccentFold::fold('Instrucción'));
    }

    // ─── sqlTranslatePair ─────────────────────────────────────────────────────

    public function test_sql_translate_pair_returns_two_strings(): void
    {
        [$from, $to] = SearchAccentFold::sqlTranslatePair();

        $this->assertIsString($from);
        $this->assertIsString($to);
    }

    public function test_sql_translate_pair_same_length(): void
    {
        [$from, $to] = SearchAccentFold::sqlTranslatePair();

        $this->assertSame(mb_strlen($from, 'UTF-8'), mb_strlen($to, 'UTF-8'));
    }

    public function test_sql_translate_pair_is_not_empty(): void
    {
        [$from, $to] = SearchAccentFold::sqlTranslatePair();

        $this->assertNotEmpty($from);
        $this->assertNotEmpty($to);
    }

    // ─── sqlFoldedLowerColumn ─────────────────────────────────────────────────

    public function test_sql_folded_lower_column_returns_array(): void
    {
        $result = SearchAccentFold::sqlFoldedLowerColumn('name');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_sql_folded_lower_column_expression_contains_translate(): void
    {
        [$expr] = SearchAccentFold::sqlFoldedLowerColumn('name');

        $this->assertStringContainsString('translate', $expr);
        $this->assertStringContainsString('lower(name)', $expr);
    }

    public function test_sql_folded_lower_column_expression_contains_replace_for_ligatures(): void
    {
        [$expr] = SearchAccentFold::sqlFoldedLowerColumn('title');

        $this->assertStringContainsString('replace', $expr);
        $this->assertStringContainsString('œ', $expr);
        $this->assertStringContainsString('æ', $expr);
        $this->assertStringContainsString('ß', $expr);
    }

    public function test_sql_folded_lower_column_bindings_are_from_translate_pair(): void
    {
        [$from, $to] = SearchAccentFold::sqlTranslatePair();
        [, $bindings] = SearchAccentFold::sqlFoldedLowerColumn('name');

        $this->assertSame($from, $bindings[0]);
        $this->assertSame($to, $bindings[1]);
    }

    // ─── escapeLike ───────────────────────────────────────────────────────────

    public function test_escape_like_escapes_percent(): void
    {
        $this->assertSame('100\%', SearchAccentFold::escapeLike('100%'));
    }

    public function test_escape_like_escapes_underscore(): void
    {
        $this->assertSame('foo\_bar', SearchAccentFold::escapeLike('foo_bar'));
    }

    public function test_escape_like_escapes_backslash(): void
    {
        $this->assertSame('foo\\\\bar', SearchAccentFold::escapeLike('foo\\bar'));
    }

    public function test_escape_like_leaves_plain_string_unchanged(): void
    {
        $this->assertSame('hello', SearchAccentFold::escapeLike('hello'));
    }

    public function test_escape_like_empty_string(): void
    {
        $this->assertSame('', SearchAccentFold::escapeLike(''));
    }

    // ─── translateCharMap (via fold consistency) ──────────────────────────────

    /**
     * Each character in the map must fold to itself (idempotency).
     */
    public function test_fold_is_idempotent(): void
    {
        $words = ['angel', 'instruccion', 'hello', 'ss', 'oe', 'ae'];

        foreach ($words as $word) {
            $this->assertSame($word, SearchAccentFold::fold($word), "fold is not idempotent for '{$word}'");
        }
    }
}
