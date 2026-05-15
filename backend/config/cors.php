<?php

/*
 * Maya — CORS configuration
 *
 * Origen permitido se construye desde env vars: el regex acepta cualquier
 * subdominio del DOMAIN_SUFFIX configurado (cualquier slot del ecosystem).
 * NUNCA hardcodear IPs o slot prefixes — toda config viene del .env.
 */

$corsDomain = env('CORS_REGEX_DOMAIN', 'localhost');
$corsDomainEscaped = preg_quote($corsDomain, '#');
$additional = array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ADDITIONAL_ORIGINS', 'http://localhost:5173,http://localhost:5174,http://localhost:5175,http://localhost:5176'))
));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $additional,

    'allowed_origins_patterns' => [
        // Acepta cualquier subdominio del dominio configurado (cubre todos los slots).
        '#^https?://[\w.-]+\.' . $corsDomainEscaped . '(:\d+)?$#',
        // Localhost con cualquier puerto.
        '#^http://localhost(:\d+)?$#',
        // Hostnames de servicio Docker (maya_authorization.localhost, etc.).
        '#^https?://maya_(authorization|dashboard|dms|logs|audit)\.localhost(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Type', 'Cache-Control'],

    'max_age' => 0,

    'supports_credentials' => false,
];
