<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nivel de visibilidad de una plantilla normativa (persistido como string en BD).
 */
enum TemplateVisibilityLevel: string
{
    case Global = 'global';
    case StudyType = 'study_type';
    case Study = 'study';
    case Module = 'module';
    case Team = 'team';
    case Personal = 'personal';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
