<?php

declare(strict_types=1);

namespace App\Notifications\Rules;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Translates a RuleConditions value object into a Laravel query builder scoped
 * to a single whitelisted FDW table.
 *
 * Security model:
 *  - `table` must be in ALLOWED_TABLES whitelist (prevents cross-table injection).
 *  - `field` must match /^[a-z_][a-z0-9_]*$/ (prevents column name injection).
 *  - Only `value` is bound as a parameterized query binding.
 */
final class ConditionCompiler
{
    /**
     * FDW tables the generic evaluator is allowed to query.
     * Add tables here as needed; never accept caller-supplied identifiers directly.
     */
    public const ALLOWED_TABLES = [
        'entity_versions',
        'documents',
        'templates',
        'document_reviews',
        'document_blocks',
        'template_reviewers',
        'template_document_reviewers',
        'comments',
        'anchored_comments',
        'comment_reads',
        'document_shares',
        'document_versions',
        'themes',
    ];

    private const FIELD_PATTERN = '/^[a-z_][a-z0-9_]*$/';

    /**
     * Build a query builder for `$table` filtered by `$conditions`.
     * All items must target the same table (v1 restriction).
     *
     * @throws InvalidArgumentException when table or field fails whitelisting.
     */
    public function compile(RuleConditions $conditions): Builder
    {
        $table = $this->resolveTable($conditions);
        $query = DB::table($table);

        if ($conditions->logic === 'AND') {
            foreach ($conditions->items as $item) {
                $query = $this->applyItem($query, $item, 'and');
            }
        } else {
            $query->where(function (Builder $q) use ($conditions): void {
                foreach ($conditions->items as $item) {
                    $q = $this->applyItem($q, $item, 'or');
                }
            });
        }

        return $query;
    }

    /**
     * V1: all items must share the same table; returns that table name.
     *
     * @throws InvalidArgumentException
     */
    private function resolveTable(RuleConditions $conditions): string
    {
        $tables = array_unique(array_map(fn (ConditionItem $i) => $i->table, $conditions->items));

        if (count($tables) !== 1) {
            throw new InvalidArgumentException(
                'All condition items must target the same table (multi-table joins not supported in v1).'
            );
        }

        return $this->validateTable($tables[0]);
    }

    /** @throws InvalidArgumentException */
    private function validateTable(string $table): string
    {
        if (! in_array($table, self::ALLOWED_TABLES, true)) {
            throw new InvalidArgumentException("Table '{$table}' is not allowed in condition rules.");
        }

        return $table;
    }

    /** @throws InvalidArgumentException */
    private function validateField(string $field): string
    {
        if (! preg_match(self::FIELD_PATTERN, $field)) {
            throw new InvalidArgumentException("Field '{$field}' contains invalid characters.");
        }

        return $field;
    }

    private function applyItem(Builder $query, ConditionItem $item, string $boolean): Builder
    {
        $field = $this->validateField($item->field);
        $op    = $item->op;

        return match (true) {
            $op === 'eq'            => $query->where($field, '=', $item->value, $boolean),
            $op === 'ne'            => $query->where($field, '!=', $item->value, $boolean),
            $op === 'gt'            => $query->where($field, '>', $item->value, $boolean),
            $op === 'lt'            => $query->where($field, '<', $item->value, $boolean),
            $op === 'gte'           => $query->where($field, '>=', $item->value, $boolean),
            $op === 'lte'           => $query->where($field, '<=', $item->value, $boolean),
            $op === 'contains'      => $query->where($field, 'ilike', '%'.$item->value.'%', $boolean),
            $op === 'starts_with'   => $query->where($field, 'ilike', $item->value.'%', $boolean),
            $op === 'ends_with'     => $query->where($field, 'ilike', '%'.$item->value, $boolean),
            $op === 'in'            => $query->whereIn($field, (array) $item->value, $boolean),
            $op === 'not_in'        => $query->whereNotIn($field, (array) $item->value, $boolean),
            $op === 'is_null'       => $query->whereNull($field, $boolean),
            $op === 'is_not_null'   => $query->whereNotNull($field, $boolean),
            $op === 'older_than_days' => $query->where(
                $field, '<=', Carbon::now()->subDays((int) $item->value)->toDateTimeString(), $boolean
            ),
            $op === 'within_days'   => $query->where(
                $field, '>=', Carbon::now()->subDays((int) $item->value)->toDateTimeString(), $boolean
            ),
            default => $query,
        };
    }
}
