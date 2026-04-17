<?php

namespace App\Enums;

enum BlockState: string
{
    case Optional   = 'optional';
    case Editable   = 'editable';
    case Modifiable = 'modifiable';
    case Locked     = 'locked';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
