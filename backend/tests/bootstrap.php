<?php

declare(strict_types=1);

/*
| PHPUnit carga este fichero tras leer phpunit.xml. Fijamos aquí la BD de tests para que
| Laravel al leer .env no sustituya (Dotenv no pisa variables ya definidas en el proceso).
|
| DMS usa SQL específico de Postgres (GREATEST, jsonb operators, jsonb_path) en repos —
| los tests DEBEN correr contra Postgres real, no SQLite. La BD maya_dms_test es exclusiva
| de tests y se recrea con RefreshDatabase en cada caso.
|
| Portabilidad entre slots: el host/puerto/usuario/password de Postgres cambian por slot
| (p. ej. el contenedor es `maya-<slot>-postgres`, no un nombre fijo). El contenedor backend
| ya exporta los DB_* correctos del slot, así que aquí los RESPETAMOS si están presentes y
| sólo aplicamos un fallback razonable cuando faltan (p. ej. CI). Lo único que SÍ forzamos
| es la BD de tests (`maya_dms_test`), distinta de la de runtime, para no pisar datos reales.
*/

// Valores forzados: críticos para aislar los tests de la BD de runtime.
//  - DB_DATABASE: la BD de tests (maya_dms_test) es distinta de la de runtime (maya_dms_db).
//  - DB_USERNAME/PASSWORD: `maya` es el rol superusuario del slot, dueño-agnóstico; evita
//    "permission denied" sobre tablas de maya_dms_test creadas en corridas previas. El rol
//    de runtime (maya_dms_user) NO sirve aquí porque no es dueño de esas tablas.
$forcedEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'pgsql',
    'DB_DATABASE' => 'maya_dms_test',
    'DB_USERNAME' => 'maya',
    'DB_PASSWORD' => 'secret',
    'DB_URL' => '',
];

// Host/puerto: cambian por slot (el contenedor es `maya-<slot>-postgres`). Respetamos el
// valor que el contenedor backend ya exporta; fallback sólo si ausente (p. ej. CI).
// `postgres` es un alias de red común a todos los slots; sirve como último recurso.
$connectionEnv = [
    'DB_HOST' => getenv('DB_HOST') ?: 'postgres',
    'DB_PORT' => getenv('DB_PORT') ?: '5432',
];

foreach ($forcedEnv + $connectionEnv as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';
