<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Maya\Platform\Providers\SharedPlatformServiceProvider;

return [
    AppServiceProvider::class,
    // Registra el comando compartido db:generate-seeders (sustituye al
    // App\Console\Commands\GenerateSeedersFromDatabase local, eliminado).
    // Explícito porque el override de vendor en contenedor no regenera el
    // manifest de package discovery.
    SharedPlatformServiceProvider::class,
];
