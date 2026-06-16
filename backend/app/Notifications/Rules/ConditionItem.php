<?php

declare(strict_types=1);

namespace App\Notifications\Rules;

/**
 * Single condition row: { table, field, op, value }.
 */
final readonly class ConditionItem
{
    /** @param string|int|list<string>|null $value */
    public function __construct(
        public string $table,
        public string $field,
        public string $op,
        public mixed $value,
    ) {}

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): ?self
    {
        $table = trim((string) ($raw['table'] ?? ''));
        $field = trim((string) ($raw['field'] ?? ''));
        $op    = (string) ($raw['op'] ?? '');

        if ($table === '' || $field === '' || ! in_array($op, ConditionOperators::ALL, true)) {
            return null;
        }

        $value = $raw['value'] ?? null;

        return new self($table, $field, $op, $value);
    }
}
