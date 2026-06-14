<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Buscador de candidatos a validador de documento.
 *
 * Requiere `document.show`.
 */
class DocumentReviewerCandidatesRequest extends AbstractReviewerCandidatesRequest
{
    protected function permission(): string
    {
        return 'document.show';
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('users.search.document_reviewers_forbidden'));
    }
}
