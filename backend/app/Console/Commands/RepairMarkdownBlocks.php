<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DocumentBlock;
use App\Models\TemplateBlock;
use App\Support\MarkdownBlockRepair;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * One-off data repair: converts Markdown that was stored as literal plain text
 * inside block content into real TipTap nodes (fixes "## " / "**bold**" showing
 * verbatim in previews). Non-destructive, idempotent, supports --dry-run.
 *
 * Writes with saveQuietly() so it does NOT re-fire observers/audit events for
 * historical data. Use --dry-run first to review the impact.
 */
final class RepairMarkdownBlocks extends Command
{
    protected $signature = 'blocks:repair-markdown
        {--dry-run : Report what would change without writing}
        {--include-codeblocks : Also convert codeBlocks whose text is prose-Markdown}
        {--limit=0 : Max rows to scan per table (0 = all)}';

    protected $description = 'Convierte markdown literal almacenado en bloques (TipTap) a nodos reales.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $includeCode = (bool) $this->option('include-codeblocks');
        $limit = (int) $this->option('limit');

        if ($dry) {
            $this->warn('DRY-RUN: no se escribirá nada.');
        }

        $stats = [
            'template_blocks.default_content' => 0,
            'template_blocks.description' => 0,
            'document_blocks.content' => 0,
        ];

        $this->scan(
            TemplateBlock::query(),
            ['default_content', 'description'],
            'template_blocks',
            $dry,
            $includeCode,
            $limit,
            $stats,
        );
        $this->scan(
            DocumentBlock::query(),
            ['content'],
            'document_blocks',
            $dry,
            $includeCode,
            $limit,
            $stats,
        );

        $this->newLine();
        $this->table(
            ['Campo', 'Filas cambiadas'],
            array_map(static fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats)),
        );

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, int>  $stats
     */
    private function scan(
        $query,
        array $fields,
        string $table,
        bool $dry,
        bool $includeCode,
        int $limit,
        array &$stats,
    ): void {
        $scanned = 0;
        $query->orderBy('id')->chunkById(200, function ($rows) use ($fields, $table, $dry, $includeCode, $limit, &$stats, &$scanned) {
            foreach ($rows as $block) {
                if ($limit > 0 && $scanned >= $limit) {
                    return false;
                }
                $scanned++;

                $dirty = false;
                foreach ($fields as $field) {
                    $value = $block->{$field};
                    if (! is_array($value)) {
                        continue;
                    }
                    $result = $this->repairField($value, $includeCode);
                    if ($result['changed']) {
                        $stats["{$table}.{$field}"]++;
                        $block->{$field} = $result['value'];
                        $dirty = true;
                        $this->line("  [{$table}#{$block->id}] {$field}: markdown → nodos");
                    }
                }

                if ($dirty && ! $dry) {
                    $this->persist($block);
                }
            }

            return true;
        });
    }

    /**
     * Handles both persisted shapes: a wrapped doc `{type:doc,content:[...]}`
     * and a bare content array.
     *
     * @param  array<mixed>  $value
     * @return array{value: array<mixed>, changed: bool}
     */
    private function repairField(array $value, bool $includeCode): array
    {
        if (($value['type'] ?? null) === 'doc' && is_array($value['content'] ?? null)) {
            $result = MarkdownBlockRepair::repair($value['content'], $includeCode);
            $value['content'] = $result['content'];

            return ['value' => $value, 'changed' => $result['changed']];
        }

        if (array_is_list($value)) {
            $result = MarkdownBlockRepair::repair($value, $includeCode);

            return ['value' => $result['content'], 'changed' => $result['changed']];
        }

        return ['value' => $value, 'changed' => false];
    }

    private function persist(Model $block): void
    {
        // saveQuietly: skip observers/audit for this historical backfill.
        $block->saveQuietly();
    }
}
