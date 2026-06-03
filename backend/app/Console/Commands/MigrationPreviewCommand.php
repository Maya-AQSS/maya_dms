<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\BlockNoteHtmlRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Maya\Editor\Renderers\BlockNoteToTiptap;
use Maya\Editor\Renderers\TiptapHtmlRenderer;

/**
 * Pre-migration diff inspector — renders a single row through BOTH renderers
 * and prints the resulting HTML side by side. Used by QA before running the
 * actual data migration.
 *
 * Replaces the v2 admin endpoint `/api/v1/admin/migration/preview` (council
 * recommendation: developer tooling does not belong on the public API).
 */
final class MigrationPreviewCommand extends Command
{
    protected $signature = 'migration:preview
        {--table=template_blocks : One of template_blocks|document_blocks|block_versions}
        {--id= : Row id to preview}
        {--diff : Print only a unified diff, not full HTML}';

    protected $description = 'Render a single row through BlockNote and TipTap renderers and compare.';

    public function handle(): int
    {
        $table = (string) $this->option('table');
        $id = $this->option('id');
        if (! in_array($table, ['template_blocks', 'document_blocks', 'block_versions'], true)) {
            $this->error("Invalid table: {$table}");
            return self::FAILURE;
        }
        if ($id === null || $id === '') {
            $this->error('--id is required');
            return self::FAILURE;
        }

        $row = DB::table($table)->where('id', $id)->first();
        if ($row === null) {
            $this->error("Row not found: {$table}#{$id}");
            return self::FAILURE;
        }

        $contentColumn = $table === 'template_blocks' ? 'default_content' : 'content';
        $raw = $row->{$contentColumn} ?? null;
        if ($raw === null) {
            $this->warn("Row has no content in column `{$contentColumn}`.");
            return self::SUCCESS;
        }

        $blocks = is_string($raw) ? json_decode($raw, true) : (array) $raw;
        if (! is_array($blocks)) {
            $this->error('Content is not a JSON array.');
            return self::FAILURE;
        }

        $legacyHtml = BlockNoteHtmlRenderer::renderBlocks($blocks);
        $tiptapDoc = BlockNoteToTiptap::convert($blocks);
        $tiptapHtml = TiptapHtmlRenderer::renderDoc($tiptapDoc);

        if ($legacyHtml === $tiptapHtml) {
            $this->info('OK — outputs are byte-identical.');
            return self::SUCCESS;
        }

        if ($this->option('diff')) {
            $this->line('--- legacy ---');
            $this->line($legacyHtml);
            $this->line('--- tiptap ---');
            $this->line($tiptapHtml);
        } else {
            $this->newLine();
            $this->line('Legacy HTML:');
            $this->line($legacyHtml);
            $this->newLine();
            $this->line('TipTap HTML:');
            $this->line($tiptapHtml);
        }

        $this->newLine();
        $this->warn('Outputs differ. Investigate before migrating.');
        return self::FAILURE;
    }
}
