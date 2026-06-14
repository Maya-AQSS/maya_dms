<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Buscador de candidatos a validador de plantilla normativa.
 *
 * Requiere `template.show`.
 */
class ReviewerCandidatesRequest extends AbstractReviewerCandidatesRequest
{
    protected function permission(): string
    {
        return 'template.show';
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('users.search.template_reviewers_forbidden'));
    }
}
