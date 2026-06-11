<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Headers HTTP compartidos por los tres preview controllers (Document, Template, Theme).
 *
 * El string CSP permite paged.js servido desde el mismo origen (/vendor/pagedjs/)
 * sin CDN externo ni eval. Se define una sola vez para evitar divergencias silenciosas.
 */
final class PreviewHeaders
{
    public const CSP = "default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; script-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'none'";

    /**
     * Headers estándar para una respuesta HTML de preview (paged.js).
     *
     * @return array<string, string>
     */
    public static function forHtml(): array
    {
        return [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => self::CSP,
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
