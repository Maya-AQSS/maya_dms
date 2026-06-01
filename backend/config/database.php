<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'pgsql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | FDW — Foreign Data Wrapper connections (read-only, production only)
    |--------------------------------------------------------------------------
    | En local/testing se crean tablas stub en las migraciones.
    | En producción se configura postgres_fdw apuntando a la DB origen.
    */

    'fdw' => [

        // Catálogo de usuarios — FDW read-only sobre odoo.public.v_app_users.
        // Migración centralizada en `maya-shared-profile-laravel`. Estos defaults
        // funcionan en local; en staging/prod los env vars sobreescriben.
        'users' => [
            'host'      => env('FDW_USERS_HOST', env('DB_HOST', 'maya_infra_postgres')),
            'port'      => env('FDW_USERS_PORT', '5432'),
            'database'  => env('FDW_USERS_DATABASE', 'odoo'),
            'username'  => env('FDW_USERS_USERNAME', 'maya'),
            'password'  => env('FDW_USERS_PASSWORD', 'secret'),
            'schema'    => env('FDW_USERS_SCHEMA', 'public'),
            'table'     => env('FDW_USERS_TABLE', 'v_app_users'),
            'cache_ttl' => (int) env('FDW_USERS_CACHE_TTL', 900),
        ],

        // Catálogo de equipos — FDW read-only sobre odoo.public.v_dms_teams.
        'teams' => [
            'host'     => env('FDW_TEAMS_HOST', env('DB_HOST', 'maya_infra_postgres')),
            'port'     => env('FDW_TEAMS_PORT', '5432'),
            'database' => env('FDW_TEAMS_DATABASE', 'odoo'),
            'username' => env('FDW_TEAMS_USERNAME', 'maya'),
            'password' => env('FDW_TEAMS_PASSWORD', 'secret'),
            'schema'   => env('FDW_TEAMS_SCHEMA', 'public'),
            'table'    => env('FDW_TEAMS_TABLE', 'v_dms_teams'),
        ],

        // Membresías de equipo — FDW read-only sobre odoo.public.v_dms_team_members.
        'team_members' => [
            'host'     => env('FDW_TEAM_MEMBERS_HOST', env('DB_HOST', 'maya_infra_postgres')),
            'port'     => env('FDW_TEAM_MEMBERS_PORT', '5432'),
            'database' => env('FDW_TEAM_MEMBERS_DATABASE', 'odoo'),
            'username' => env('FDW_TEAM_MEMBERS_USERNAME', 'maya'),
            'password' => env('FDW_TEAM_MEMBERS_PASSWORD', 'secret'),
            'schema'   => env('FDW_TEAM_MEMBERS_SCHEMA', 'public'),
            'table'    => env('FDW_TEAM_MEMBERS_TABLE', 'v_dms_team_members'),
        ],

        // Permisos resueltos por usuario — FDW de solo lectura sobre
        // maya_auth.v_portal_user_permissions (cross-app: incluye `*.login`
        // de todas las apps para decidir acceso/redirect al portal).
        // Lectura vía `user_resolved_permissions` local (vista pass-through).
        'user_permissions' => [
            'host'        => env('FDW_USER_PERMISSIONS_HOST', env('DB_HOST', 'maya_infra_postgres')),
            'port'        => env('FDW_USER_PERMISSIONS_PORT', '5432'),
            'database'    => env('FDW_USER_PERMISSIONS_DATABASE', 'maya_auth'),
            'username'    => env('FDW_USER_PERMISSIONS_USERNAME', 'maya'),
            'password'    => env('FDW_USER_PERMISSIONS_PASSWORD', 'secret'),
            'schema'      => env('FDW_USER_PERMISSIONS_SCHEMA', 'public'),
            'remote_view' => env('FDW_USER_PERMISSIONS_REMOTE_VIEW', 'v_portal_user_permissions'),
        ],

        // Catálogo de permisos DMS — FDW de solo lectura sobre maya_auth.v_dms_permissions.
        // En local se apunta al mismo Postgres (BD maya_auth, usuario maya).
        'permissions' => [
            'host'     => env('FDW_PERMISSIONS_HOST', env('DB_HOST', 'maya_infra_postgres')),
            'port'     => env('FDW_PERMISSIONS_PORT', '5432'),
            'database' => env('FDW_PERMISSIONS_DATABASE', 'maya_auth'),
            'username' => env('FDW_PERMISSIONS_USERNAME', 'maya'),
            'password' => env('FDW_PERMISSIONS_PASSWORD', 'secret'),
            'schema'   => env('FDW_PERMISSIONS_SCHEMA', 'public'),
            'table'    => env('FDW_PERMISSIONS_TABLE', 'v_dms_permissions'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
