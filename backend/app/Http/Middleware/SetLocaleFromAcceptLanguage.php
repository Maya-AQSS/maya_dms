<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ajusta {@see App::getLocale()} segun `Accept-Language` para que `__()` en la API
 * devuelva cadenas en el idioma del cliente (dentro de `config('app.supported_locales')`).
 */
class SetLocaleFromAcceptLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string>|array<int, string> $supported */
        $supported = config('app.supported_locales', ['es', 'en']);
        $supported = array_values(array_filter($supported, static fn ($loc): bool => is_string($loc) && $loc !== ''));

        $locale = $supported !== []
            ? $request->getPreferredLanguage($supported)
            : null;

        if ($locale !== null && $locale !== '') {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
