<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Plegado de acentos alineado entre PHP y SQL (translate + replace) para LIKE
 * en PostgreSQL (prod) y SQLite (tests).
 */
final class SearchAccentFold
{
    /**
     * Map de caracteres UTF-8 (tras mb_strtolower) → caracteres base (translate 1:1).
     * 
     * @return array<string, string> un carácter (tras mb_strtolower) → un carácter base (translate 1:1)
     */
    private static function translateCharMap(): array
    {
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        $pairs = [
            ['á', 'a'], ['à', 'a'], ['â', 'a'], ['ã', 'a'], ['ä', 'a'], ['å', 'a'], ['ā', 'a'], ['ă', 'a'],
            ['ą', 'a'], ['ǎ', 'a'], ['ǟ', 'a'], ['ǡ', 'a'], ['ǻ', 'a'],
            ['é', 'e'], ['è', 'e'], ['ê', 'e'], ['ë', 'e'], ['ē', 'e'], ['ė', 'e'], ['ę', 'e'], ['ě', 'e'],
            ['í', 'i'], ['ì', 'i'], ['î', 'i'], ['ï', 'i'], ['ī', 'i'], ['į', 'i'], ['ǐ', 'i'],
            ['ó', 'o'], ['ò', 'o'], ['ô', 'o'], ['õ', 'o'], ['ö', 'o'], ['ō', 'o'], ['ő', 'o'], ['ǒ', 'o'],
            ['ǫ', 'o'], ['ǭ', 'o'],
            ['ú', 'u'], ['ù', 'u'], ['û', 'u'], ['ü', 'u'], ['ū', 'u'], ['ů', 'u'], ['ű', 'u'], ['ǔ', 'u'],
            ['ǖ', 'u'], ['ǘ', 'u'], ['ǚ', 'u'], ['ǜ', 'u'],
            ['ñ', 'n'], ['ń', 'n'], ['ň', 'n'], ['ņ', 'n'],
            ['ç', 'c'], ['ć', 'c'], ['č', 'c'], ['ĉ', 'c'], ['ċ', 'c'],
            ['ý', 'y'], ['ÿ', 'y'], ['ỳ', 'y'], ['ŷ', 'y'], ['ȳ', 'y'],
            ['ß', 's'],
            ['ł', 'l'], ['ľ', 'l'], ['ļ', 'l'], ['ŀ', 'l'],
            ['đ', 'd'], ['ď', 'd'],
            ['ř', 'r'], ['ŕ', 'r'], ['ŗ', 'r'],
            ['ś', 's'], ['š', 's'], ['ş', 's'], ['ș', 's'],
            ['ź', 'z'], ['ž', 'z'], ['ż', 'z'],
            ['ğ', 'g'], ['ǧ', 'g'], ['ģ', 'g'],
            ['ķ', 'k'],
        ];

        $map = [];
        foreach ($pairs as [$from, $to]) {
            if (mb_strlen($from, 'UTF-8') !== 1 || mb_strlen($to, 'UTF-8') !== 1) {
                continue;
            }
            if (! isset($map[$from])) {
                $map[$from] = $to;
            }
        }

        return $map;
    }

    /**
     * Plegado de acentos: œ/æ → oe/ae y caracteres UTF-8 (tras mb_strtolower) → caracteres base (translate 1:1).
     * 
     * @param string $value el valor a plegar
     * @return string el valor plegado
     */
    public static function fold(string $value): string
    {
        $s = mb_strtolower(trim($value), 'UTF-8');
        $s = str_replace(['ß', 'ẞ'], 'ss', $s);
        $s = strtr($s, ['œ' => 'oe', 'æ' => 'ae']);
        $s = strtr($s, self::translateCharMap());

        return $s;
    }

    /**
     * Argumentos para translate(from, to) — solo pares 1:1.
     * 
     * @return array{0: string, 1: string} argumentos para translate(from, to) — solo pares 1:1
     */
    public static function sqlTranslatePair(): array
    {
        $m = self::translateCharMap();
        ksort($m, SORT_STRING);
        $from = '';
        $to = '';
        foreach ($m as $f => $t) {
            $from .= $f;
            $to .= $t;
        }

        return [$from, $to];
    }

    /**
     * Expresión SQL: columna en minúsculas, sin acentos (aprox.) y œ/æ → oe/ae.
     *
     * @return array{0: string, 1: list<string>} SQL y bindings [from, to] del translate
     */
    public static function sqlFoldedLowerColumn(string $columnSql): array
    {
        [$from, $to] = self::sqlTranslatePair();
        $expr = "translate(lower({$columnSql}), ?, ?)";
        $expr = "replace(replace(replace(replace({$expr}, 'œ', 'oe'), 'æ', 'ae'), 'ß', 'ss'), 'ẞ', 'ss')";

        return [$expr, [$from, $to]];
    }

    /**
     * Escapar comodines para LIKE.
     * 
     * @param string $value el valor a escapar
     * @return string el valor escapado
     */
    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
