<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\BlockNoteHtmlRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Maya\Editor\Renderers\BlockNoteToTiptap;
use Maya\Editor\Renderers\TiptapHtmlRenderer;
use Throwable;

/**
 * Migrates `content` payloads in the three block-bearing tables from
 * the legacy BlockNote JSON shape to the new ProseMirror/TipTap shape.
 *
 * For each row:
 *   1. Copy original content to `content_legacy_blocknote`.
 *   2. Convert with `BlockNoteToTiptap::convert`.
 *   3. Render both forms through their respective HTML renderer.
 *   4. If HTML differs → skip the row, log a warning, do NOT write.
 *   5. If identical → overwrite `content` with the converted doc.
 *
 * If at the end of the run every row was migrated AND no divergences
 * were logged, the command drops the legacy backup column. This is the
 * "30-day backup" decision reversed (council recommendation): keeping
 * the legacy column "for safety" creates dead weight; rollback in
 * production is handled by the `EDITOR_BACKEND` feature flag, not by
 * the column.
 *
 * Run with `--dry-run` first on staging to see counts without writing.
 */
final class BlockNoteMigrateToTiptapCommand extends Command
{
    protected $signature = 'blocknote:migrate-to-tiptap
        {--dry-run : Read & compare, do not write}
        {--table= : Limit to one table (template_blocks|document_blocks|block_versions)}
        {--batch=1000 : Rows per transaction}
        {--no-drop : Skip dropping legacy column even on success}';

    protected $description = 'Convert BlockNote JSON content to TipTap ProseMirror across all block tables.';

    private const TABLES = [
        'template_blocks' => 'default_content',
        'document_blocks' => 'content',
        'block_versions' => 'content',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $batch = max(1, (int) $this->option('batch'));
        $only = $this->option('table');

        $tables = $only !== null ? [$only => self::TABLES[$only] ?? null] : self::TABLES;

        $totalMigrated = 0;
        $totalDivergences = 0;
        $totalSkipped = 0;
        $totalRows = 0;

        foreach ($tables as $table => $column) {
            if ($column === null || ! Schema::hasTable($table)) {
                $this->warn("Skipping {$table} (missing).");
                continue;
            }
            if (! Schema::hasColumn($table, 'content_legacy_blocknote')) {
                $this->error("Column content_legacy_blocknote missing from {$table}. Run migration 2026_06_01_000001 first.");
                return self::FAILURE;
            }

            $this->info("Migrating {$table}.{$column}…");

            DB::table($table)
                ->whereNull('content_legacy_blocknote')
                ->orderBy('id')
                ->chunkById($batch, function ($rows) use ($table, $column, $dryRun, &$totalMigrated, &$totalDivergences, &$totalSkipped, &$totalRows) {
                    DB::transaction(function () use ($rows, $table, $column, $dryRun, &$totalMigrated, &$totalDivergences, &$totalSkipped, &$totalRows) {
                        foreach ($rows as $row) {
                            $totalRows++;
                            $raw = $row->{$column} ?? null;
                            if ($raw === null) {
                                $totalSkipped++;
                                continue;
                            }
                            $blocks = is_string($raw) ? json_decode($raw, true) : (array) $raw;
                            if (! is_array($blocks)) {
                                $totalSkipped++;
                                Log::channel('blocknote-migration')->warning('Non-array content', ['table' => $table, 'id' => $row->id]);
                                continue;
                            }

                            try {
                                $legacyHtml = BlockNoteHtmlRenderer::renderBlocks($blocks);
                                $tiptapDoc = BlockNoteToTiptap::convert($blocks);
                                $tiptapHtml = TiptapHtmlRenderer::renderDoc($tiptapDoc);
                            } catch (Throwable $e) {
                                $totalDivergences++;
                                Log::channel('blocknote-migration')->error('Renderer exception', [
                                    'table' => $table,
                                    'id' => $row->id,
                                    'error' => $e->getMessage(),
                                ]);
                                continue;
                            }

                            if ($legacyHtml !== $tiptapHtml) {
                                $totalDivergences++;
                                Log::channel('blocknote-migration')->warning('HTML divergence', [
                                    'table' => $table,
                                    'id' => $row->id,
                                ]);
                                continue;
                            }

                            if (! $dryRun) {
                                DB::table($table)
                                    ->where('id', $row->id)
                                    ->update([
                                        'content_legacy_blocknote' => is_string($raw) ? $raw : json_encode($raw),
                                        $column => json_encode($tiptapDoc),
                                    ]);
                            }
                            $totalMigrated++;
                        }
                    });
                });
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. rows=%d migrated=%d divergences=%d skipped=%d%s',
            $totalRows,
            $totalMigrated,
            $totalDivergences,
            $totalSkipped,
            $dryRun ? ' (DRY-RUN, no writes)' : '',
        ));

        $shouldDropColumn = ! $dryRun
            && ! $this->option('no-drop')
            && $totalDivergences === 0
            && $totalSkipped === 0;

        if ($shouldDropColumn) {
            $this->info('Zero divergences — dropping legacy backup columns now.');
            foreach ($tables as $table => $_) {
                if (Schema::hasColumn($table, 'content_legacy_blocknote')) {
                    Schema::table($table, fn ($t) => $t->dropColumn('content_legacy_blocknote'));
                }
            }
        } elseif ($totalDivergences > 0) {
            $this->warn('Legacy backup column kept — see storage/logs/blocknote-migration.log for the divergent rows.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
