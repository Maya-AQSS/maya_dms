<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Maya\Auth\Middleware\JwtMiddleware;
use Maya\Auth\Middleware\RequirePermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Block rich-content fields contain inline text nodes whose leading/trailing
        // spaces are semantically significant (spaces between bold/italic runs).
        // TrimStrings must not recurse into these fields; SanitizesBlockContent
        // handles sanitization for them after the request is parsed.
        $middleware->trimStrings(except: [
            'default_content.*',
            'description.*',
            'content.*',
        ]);
        $middleware->alias([
            'jwt' => JwtMiddleware::class,
            'permission' => RequirePermissionMiddleware::class,
        ]);
        $middleware->api(prepend: [HandleCors::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
