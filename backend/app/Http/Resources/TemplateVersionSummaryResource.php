<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * Historial de versiones publicadas (sin el JSONB de bloques).
 */
class TemplateVersionSummaryResource extends JsonResource
{
    /**
     * Convierte la versión de plantilla en un array para la respuesta JSON (sin el JSONB de bloques).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $templateId = $this->template_id ?? $this->versionable_id;
        $publishedAt = $this->published_at ?? null;
        $publishedBy = is_string($this->published_by) && $this->published_by !== '' ? $this->published_by : null;
        $authorId = $this->extractAuthorIdFromSnapshot();
        if ($authorId === null && is_string($this->created_by) && $this->created_by !== '') {
            $authorId = $this->created_by;
        }

        return [
            'id' => $this->id,
            'template_id' => $templateId,
            'version_number' => $this->version_number,
            'published_at' => $publishedAt?->toIso8601String(),
            'published_by' => $publishedBy,
            'published_by_name' => $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
            'author_name' => $authorId !== null ? $this->resolveUserNameById($authorId) : null,
            'reviewer_names' => $this->extractReviewerNamesFromSnapshot(),
            'changelog' => $this->changelog,
        ];
    }

    private function extractAuthorIdFromSnapshot(): ?string
    {
        $snapshot = $this->snapshot_data;
        if (is_string($snapshot)) {
            $decoded = json_decode($snapshot, true);
            $snapshot = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($snapshot)) {
            return null;
        }
        $authorId = data_get($snapshot, 'template.created_by');

        return is_string($authorId) && $authorId !== '' ? $authorId : null;
    }

    /** @return list<string> */
    private function extractReviewerNamesFromSnapshot(): array
    {
        $snapshot = $this->snapshot_data;
        if (is_string($snapshot)) {
            $decoded = json_decode($snapshot, true);
            $snapshot = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($snapshot)) {
            return [];
        }
        $reviewers = data_get($snapshot, 'reviewers.template_reviewers');
        if (! is_array($reviewers)) {
            return [];
        }
        $names = [];
        foreach ($reviewers as $r) {
            $userId = $r['user_id'] ?? null;
            if (! is_string($userId) || $userId === '') {
                continue;
            }
            $name = $this->resolveUserNameById($userId);
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function resolveUserNameById(string $userId): ?string
    {
        static $cache = [];
        if (array_key_exists($userId, $cache)) {
            return $cache[$userId];
        }
        $name = DB::table('users')->where('id', $userId)->value('name');
        $cache[$userId] = is_string($name) && trim($name) !== '' ? trim($name) : null;

        return $cache[$userId];
    }
}
