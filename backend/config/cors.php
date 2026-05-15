<?php

/*
 * Maya — CORS configuration
 *
 * Cualquiera de las 4 SPAs Maya puede llamar a cualquier API Maya
 * (favoritos compartidos, notificaciones, IdP único). Keycloak emite el token,
 * el backend valida JWT en cada request — el origin es solo el primer filtro.
 */

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [
        '#^https://[a-z0-9-]+\.192\.168\.2\.1\.nip\.io$#',
        '#^http://localhost:\d+$#',
        '#^https?://maya_(authorization|dashboard|dms|logs|audit)\.localhost(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Type', 'Cache-Control'],

    'max_age' => 0,

    'supports_credentials' => false,
];
