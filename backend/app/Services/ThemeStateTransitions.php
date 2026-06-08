<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ThemeStatus;
use Illuminate\Validation\ValidationException;

/**
 * Máquina de estados de los themes — única fuente de verdad del grafo de
 * transiciones permitido. El estado avanza por el flujo hasta publicar; una
 * vez publicado no puede volver a borrador.
 *
 *   draft     → published   (publicar)
 *   published → archived     (archivar)
 *   published ↛ draft        (PROHIBIDO)
 *   draft     ↛ archived     (hay que publicar antes)
 *   archived  → ∅            (terminal)
 */
final class ThemeStateTransitions
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        ThemeStatus::Draft->value => [ThemeStatus::Published->value],
        ThemeStatus::Published->value => [ThemeStatus::Archived->value],
        ThemeStatus::Archived->value => [],
    ];

    /**
     * @return bool `true` si `$from → $to` es una transición permitida.
     */
    public static function canTransition(ThemeStatus $from, ThemeStatus $to): bool
    {
        return in_array($to->value, self::ALLOWED[$from->value] ?? [], true);
    }

    /**
     * Valida la transición; lanza 422 con error en `status` si no es válida.
     *
     * @throws ValidationException
     */
    public static function assert(ThemeStatus $from, ThemeStatus $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'status' => ["No se puede cambiar el estado de '{$from->value}' a '{$to->value}'."],
            ]);
        }
    }
}
