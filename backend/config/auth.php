<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'api'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'jwt-token',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | JWT / JWKS Configuration (Zero Trust — RS256)
    |--------------------------------------------------------------------------
    */

    'jwks_url' => env('JWKS_URL'),
    'jwt_audience' => env('JWT_AUDIENCE'),
    'jwt_issuer' => env('JWT_ISSUER'),

    /*
    |--------------------------------------------------------------------------
    | Equipos — roles con permiso de gestión de catálogo (realm roles Keycloak)
    |--------------------------------------------------------------------------
    */
    'team_management_roles' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'TEAM_MANAGEMENT_ROLES',
            env('GROUP_MANAGEMENT_ROLES', 'manager,super-admin,admin')
        ))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Plantillas — catálogo ampliado por permisos (global scope Template)
    |--------------------------------------------------------------------------
    |
    | Códigos de `permissions` (BD). Si el usuario tiene al menos uno, entra la
    | rama de catálogo ampliado del global scope (p. ej. ver plantillas personales
    | ajenas).
    |
    | Así se puede dar solo `templates.read` y `templates.delete` sin
    | `templates.update` (quien ve y archiva pero no edita contenido ajeno).
    |
    | Crear con visibilidad no personal, editar o borrar ajenas se resuelven en
    | {@see \App\Policies\TemplatePolicy} con `templates.create`, `templates.update`
    | y `templates.delete` por separado.
    |
    */
    'template_catalog_access_codes' => [
        'templates.read',
        'templates.update',
        'templates.delete',
    ],

];
