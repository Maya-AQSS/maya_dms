<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates\Concerns;

use App\Enums\TemplateVisibilityLevel;

/**
 * Valida campos de ámbito académico solo cuando la visibilidad los exige.
 * Personal y global no acotan por contexto académico del titular.
 */
trait ValidatesTemplateAcademicScope
{
    protected function academicScopeApplies(?string $visibilityLevel): bool
    {
        $level = TemplateVisibilityLevel::tryFrom((string) $visibilityLevel);

        return $level !== null
            && $level !== TemplateVisibilityLevel::Personal
            && $level !== TemplateVisibilityLevel::Global;
    }

    protected function teamScopeApplies(?string $visibilityLevel): bool
    {
        return TemplateVisibilityLevel::tryFrom((string) $visibilityLevel) === TemplateVisibilityLevel::Team;
    }
}
