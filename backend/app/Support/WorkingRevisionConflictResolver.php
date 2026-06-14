<?php

declare(strict_types=1);

namespace App\Support;

use App\DTOs\Versioning\WorkingRevisionConflictDto;
use App\Models\EntityVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Regla única: hay conflicto de nueva versión cuando existe publicación previa
 * y el HEAD real está en draft, in_review o rejected.
 */
final class WorkingRevisionConflictResolver
{
    /** @var list<string> */
    private const ACTIVE_STATUSES = ['draft', 'in_review', 'rejected'];

    public static function resolve(
        string $realHeadStatus,
        ?EntityVersion $latestPublished,
        ?EntityVersion $realHeadVersion,
        ?string $editorName,
    ): WorkingRevisionConflictDto {
        if ($latestPublished === null || ! in_array($realHeadStatus, self::ACTIVE_STATUSES, true)) {
            return WorkingRevisionConflictDto::none();
        }

        return new WorkingRevisionConflictDto(
            inProgress: true,
            editorName: $editorName,
            startedAt: self::formatStartedAt($realHeadVersion),
        );
    }

    public static function attachToModel(Model $model, WorkingRevisionConflictDto $conflict): void
    {
        $model->setAttribute('working_revision_in_progress', $conflict->inProgress);
        $model->setAttribute('working_revision_editor_name', $conflict->inProgress ? $conflict->editorName : null);
        $model->setAttribute(
            'working_revision_started_at',
            $conflict->inProgress ? $conflict->startedAt : null,
        );
    }

    /**
     * @return array{message: string, code: string, draft_author: ?string, started_at: ?string}
     */
    public static function toConflictResponse(WorkingRevisionConflictDto $conflict): array
    {
        return [
            'message' => __('working_revision.in_progress'),
            'code' => 'working_revision_in_progress',
            'draft_author' => $conflict->editorName,
            'started_at' => $conflict->startedAt,
        ];
    }

    private static function formatStartedAt(?EntityVersion $realHeadVersion): ?string
    {
        $startedAt = $realHeadVersion?->updated_at ?? $realHeadVersion?->created_at;

        return $startedAt instanceof Carbon ? $startedAt->toIso8601String() : null;
    }
}
