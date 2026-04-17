<?php

declare(strict_types=1);

/*
| PHPUnit carga este fichero tras leer phpunit.xml. Fijamos aquí la BD de tests para que
| Laravel al leer .env no sustituya (Dotenv no pisa variables ya definidas en el proceso).
| Objetivo: sqlite :memory: — nunca la Postgres de desarrollo ni FDW/teams de solo lectura.
*/
$testingEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
];

foreach ($testingEnv as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';
