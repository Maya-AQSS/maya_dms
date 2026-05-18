<?php

declare(strict_types=1);

/*
| PHPUnit carga este fichero tras leer phpunit.xml. Fijamos aquí la BD de tests para que
| Laravel al leer .env no sustituya (Dotenv no pisa variables ya definidas en el proceso).
|
| DMS usa SQL específico de Postgres (GREATEST, jsonb operators, jsonb_path) en repos —
| los tests DEBEN correr contra Postgres real, no SQLite. La BD maya_dms_test es exclusiva
| de tests y se recrea con RefreshDatabase en cada caso.
*/
$testingEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => 'maya_infra_postgres',
    'DB_PORT' => '5432',
    'DB_DATABASE' => 'maya_dms_test',
    'DB_USERNAME' => 'maya',
    'DB_PASSWORD' => 'secret',
    'DB_URL' => '',
];

foreach ($testingEnv as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';
