<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados posibles de un theme. El ciclo de vida lo gobierna
 * {@see \App\Services\ThemeStateTransitions}.
 */
enum ThemeStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
