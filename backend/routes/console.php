<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Evalúa reglas programadas de notificaciones diariamente a las 07:00
Schedule::command('notifications:evaluate-rules')
    ->dailyAt('07:00')
    ->withoutOverlapping();
