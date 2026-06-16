<?php

declare(strict_types=1);

namespace App\Notifications\Rules;

/**
 * Typed value object parsed from the `conditions` JSONB column.
 *
 * Shape: { "logic": "AND"|"OR", "items": [ConditionItem, ...] }
 */
final readonly class RuleConditions
{
    /** @param list<ConditionItem> $items */
    public function __construct(
        public string $logic,
        public array $items,
    ) {}

    /**
     * @param array<string, mixed>|null $raw
     */
    public static function fromArray(?array $raw): ?self
    {
        if ($raw === null) {
            return null;
        }

        $logic = in_array($raw['logic'] ?? '', ['AND', 'OR'], true) ? $raw['logic'] : 'AND';
        $items = [];

        foreach ((array) ($raw['items'] ?? []) as $item) {
            $parsed = ConditionItem::fromArray((array) $item);
            if ($parsed !== null) {
                $items[] = $parsed;
            }
        }

        if ($items === []) {
            return null;
        }

        return new self($logic, $items);
    }
}
