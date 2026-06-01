<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Inserta publicaciones de plantilla solo en {@see entity_versions} (snapshot canónico).
 */
class TemplateVersionsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('entity_versions')) {
            return;
        }

        $rows = $this->mockRows();
        if ($rows === []) {
            return;
        }

        $now = Carbon::now();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $templateId = (string) ($row['template_id'] ?? '');
            $versionNumber = (int) ($row['version_number'] ?? 0);
            if ($templateId === '' || $versionNumber < 1) {
                continue;
            }

            $template = DB::table('templates')->where('id', $templateId)->first();
            if ($template === null) {
                continue;
            }

            $headTemplate = [];
            if ($template->head_entity_version_id ?? null) {
                $headEv = DB::table('entity_versions')->where('id', $template->head_entity_version_id)->first();
                if ($headEv !== null && is_string($headEv->snapshot_data)) {
                    try {
                        $decoded = json_decode($headEv->snapshot_data, true, 512, JSON_THROW_ON_ERROR);
                        $headTemplate = is_array($decoded['template'] ?? null) ? $decoded['template'] : [];
                    } catch (\JsonException) {
                        $headTemplate = [];
                    }
                }
            }

            $blocks = $this->normalizeBlocksSnapshot($row['blocks_snapshot'] ?? []);
            $publishedBy = (string) ($row['published_by'] ?? $headTemplate['created_by'] ?? '');
            if ($publishedBy === '') {
                continue;
            }

            $publishedAt = isset($row['published_at']) && $row['published_at'] !== null
                ? Carbon::parse((string) $row['published_at'])
                : $now;

            $changelog = (string) ($row['changelog'] ?? '');
            $snapshotData = $this->buildPublishedSnapshotPayload(
                $template,
                $headTemplate,
                $versionNumber,
                $blocks,
                $this->templateReviewersSnapshot($templateId),
                $this->documentReviewersSnapshot($templateId),
            );

            $existingEntity = DB::table('entity_versions')
                ->where('versionable_type', Template::class)
                ->where('versionable_id', $templateId)
                ->where('version_number', $versionNumber)
                ->first();

            if ($existingEntity === null) {
                $explicit = $row['entity_version_id'] ?? null;
                $entityVersionId = is_string($explicit) && $explicit !== ''
                    ? $explicit
                    : (string) Str::uuid();
                DB::table('entity_versions')->insert([
                    'id' => $entityVersionId,
                    'versionable_type' => Template::class,
                    'versionable_id' => $templateId,
                    'version_number' => $versionNumber,
                    'base_version_id' => null,
                    'change_set' => null,
                    'status' => 'published',
                    'created_by' => $publishedBy,
                    'published_by' => $publishedBy,
                    'published_at' => $publishedAt,
                    'changelog' => $changelog,
                    'snapshot_data' => json_encode($snapshotData, JSON_THROW_ON_ERROR),
                    'is_snapshot_immutable' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mockRows(): array
    {
        $filePath = database_path('data/template_versions_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $rows = require $filePath;

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param  mixed  $raw
     * @return list<array<string, mixed>>
     */
    private function normalizeBlocksSnapshot(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_string($raw)) {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return [];
            }
            if (! is_array($decoded)) {
                return [];
            }

            return array_values($decoded);
        }
        if (is_array($raw)) {
            return array_values($raw);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $headTemplate  Clave `template` del snapshot cabezal (entity_versions v0).
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<array<string, mixed>>  $templateReviewers
     * @param  list<array<string, mixed>>  $documentReviewers
     * @return array<string, mixed>
     */
    private function buildPublishedSnapshotPayload(
        object $template,
        array $headTemplate,
        int $versionNumber,
        array $blocks,
        array $templateReviewers,
        array $documentReviewers,
    ): array {
        return [
            'template' => [
                'id' => (string) $template->id,
                'process_id' => (string) $template->process_id,
                'name' => (string) ($headTemplate['name'] ?? ''),
                'description' => $headTemplate['description'] ?? null,
                'visibility_level' => (string) ($headTemplate['visibility_level'] ?? 'personal'),
                'study_type_id' => $headTemplate['study_type_id'] ?? null,
                'study_id' => $headTemplate['study_id'] ?? null,
                'module_id' => $headTemplate['module_id'] ?? null,
                'team_id' => $headTemplate['team_id'] ?? null,
                'status' => 'published',
                'version' => $versionNumber,
            ],
            'blocks' => $blocks,
            'reviewers' => [
                'template_reviewers' => $templateReviewers,
                'document_reviewers' => $documentReviewers,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templateReviewersSnapshot(string $templateId): array
    {
        if (! Schema::hasTable('template_reviewers')) {
            return [];
        }

        return DB::table('template_reviewers')
            ->where('template_id', $templateId)
            ->orderBy('stage')
            ->orderBy('user_id')
            ->get()
            ->map(static function (object $r): array {
                return [
                    'user_id' => (string) $r->user_id,
                    'stage' => (int) $r->stage,
                    'status' => (string) ($r->status ?? 'pending'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function documentReviewersSnapshot(string $templateId): array
    {
        if (! Schema::hasTable('template_document_reviewers')) {
            return [];
        }

        return DB::table('template_document_reviewers')
            ->where('template_id', $templateId)
            ->orderBy('stage')
            ->orderBy('user_id')
            ->get()
            ->map(static function (object $r): array {
                return [
                    'user_id' => (string) $r->user_id,
                    'stage' => (int) $r->stage,
                ];
            })
            ->values()
            ->all();
    }
}
