<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Política de fecha límite al clonar un documento: una fecha ya vencida no debe
 * arrastrarse al clon (es para un curso nuevo), así el editor obliga a fijar una
 * nueva (validación del paso 1). Las fechas futuras o vacías se conservan.
 */
final class CloneDeadlinePolicy
{
    public static function clearIfPast(mixed $deadline): mixed
    {
        if ($deadline === null || (is_string($deadline) && trim($deadline) === '')) {
            return $deadline;
        }

        try {
            $date = $deadline instanceof DateTimeInterface
                ? CarbonImmutable::instance($deadline)
                : CarbonImmutable::parse((string) $deadline);
        } catch (Throwable) {
            return $deadline;
        }

        return $date->startOfDay()->lt(CarbonImmutable::now()->startOfDay()) ? null : $deadline;
    }
}
