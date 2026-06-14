<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * FormRequest para el buscador de candidatos a propietario de plantilla/documento.
 *
 * Misma autorización que {@see SearchUsersRequest} (`template.show` o
 * `document.show`) pero con un mensaje de error propio.
 */
class OwnerCandidatesRequest extends SearchUsersRequest
{
    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('users.search.owner_candidates_forbidden'));
    }
}
