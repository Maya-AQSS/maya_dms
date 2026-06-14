<?php

declare(strict_types=1);

namespace App\Http\Concerns;

/**
 * Si el cliente envía ?process_id= en la query, debe coincidir con el proceso
 * del recurso (doble comprobación con el contexto del árbol de procesos en front).
 */
trait ValidatesOptionalProcessContext
{
    protected function assertOptionalProcessContextMatches(?string $resourceProcessId): void
    {
        $given = request()->query('process_id');
        if ($given === null || $given === '') {
            return;
        }
        if (! is_string($resourceProcessId) || $given !== $resourceProcessId) {
            abort(403, __('auth.process_context.mismatch'));
        }
    }
}
