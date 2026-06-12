<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Maya\Auth\Middleware\JwtMiddleware;
use Maya\Auth\Middleware\RequirePermissionMiddleware;
use Maya\Http\Exceptions\JsonExceptionRenderer;
use Maya\Http\Support\CommonMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Configuración común Maya (CORS primero en el grupo api + trustProxies)
        // con las opciones propias de dms:
        // - alias jwt/permission (idénticos a la config previa);
        // - trimStrings except: los campos rich-content de bloques contienen
        //   nodos de texto inline cuyos espacios iniciales/finales son
        //   semánticamente significativos (espacios entre runs bold/italic).
        //   TrimStrings no debe recorrerlos; SanitizesBlockContent se encarga
        //   de su saneado tras parsear la request.
        // NOTA: dms antes no llamaba a trustProxies; el default del helper lo
        // activa (at: '*'), necesario tras Traefik. Ver changes.md.
        CommonMiddleware::register($middleware, [
            'jwt' => JwtMiddleware::class,
            'permission' => RequirePermissionMiddleware::class,
        ], [
            'trimStringsExcept' => [
                'default_content.*',
                'description.*',
                'content.*',
            ],
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Renderer JSON uniforme para api/* (envelope {"message": ...} +
        // {"errors": ...} en 422). dms antes usaba el render por defecto de
        // Laravel — CAMBIO FUNCIONAL, ver changes.md.
        JsonExceptionRenderer::register($exceptions);
    })->create();
