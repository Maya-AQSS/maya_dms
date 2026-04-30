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

    'allowed_origins' => [
        'http://maya_authorization.localhost',
        'http://maya_dashboard.localhost',
        'http://maya_dms.localhost',
        'http://maya_logs.localhost',
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175',
        'http://localhost:5176',
    ],

    'allowed_origins_patterns' => [
        '#^https?://maya_(authorization|dashboard|dms|logs)\.localhost(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Type', 'Cache-Control'],

    'max_age' => 0,

    'supports_credentials' => false,
];
