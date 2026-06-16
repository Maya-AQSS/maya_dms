<?php

declare(strict_types=1);

namespace App\Notifications\Rules;

/**
 * Canonical list of supported operators for the generic condition engine.
 * Mirror of the frontend ConditionOp union and the dashboard validation trait.
 */
final class ConditionOperators
{
    public const ALL = [
        'eq', 'ne',
        'gt', 'lt', 'gte', 'lte',
        'contains', 'starts_with', 'ends_with',
        'in', 'not_in',
        'is_null', 'is_not_null',
        'older_than_days', 'within_days',
    ];

    /** Operators that require no value. */
    public const NO_VALUE = ['is_null', 'is_not_null'];

    /** Operators that expect an array value. */
    public const LIST_OPS = ['in', 'not_in'];

    /** Operators that expect a non-negative integer (days). */
    public const DAYS_OPS = ['older_than_days', 'within_days'];

    private function __construct() {}
}
