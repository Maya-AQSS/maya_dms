<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\TemplateBlocks\TemplateBlockPayloadDto;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateDocumentReviewer;
use App\Models\TemplateReviewer;
use App\Repositories\Contracts\TemplateReviewerRepositoryInterface;
use Illuminate\Support\Facades\DB;

class TemplateReviewerRepository implements TemplateReviewerRepositoryInterface
{
    /**
     * Returns IDs of templates (from the given list) where the user is a reviewer.
     * Result is a flipped map: ['template_id' => true].
     *
     * @param  list<string>  $templateIds
     * @return array<string, true>
     */
    public function findTemplateIdsWithReviewer(array $templateIds, string $userId): array
    {
        /** @var array<string, true> */
        return DB::table('template_reviewers')
            ->whereIn('template_id', $templateIds)
            ->where('user_id', $userId)
            ->pluck('template_id')
            ->map(fn ($id) => (string) $id)
            ->flip()
            ->all();
    }

    /**
     * Returns true if the user is a reviewer (active, non-deleted) on the given template.
     */
    public function existsReviewerForTemplate(string $templateId, string $userId): bool
    {
        return DB::table('template_reviewers')
            ->where('template_id', $templateId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Finds a reviewer record for a template+user, or null if not found.
     */
    public function findReviewerForTemplate(string $templateId, string $userId): ?TemplateReviewer
    {
        return TemplateReviewer::query()
            ->where('template_id', $templateId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Updates the status of the reviewer identified by templateId + userId.
     */
    public function updateReviewerStatus(string $templateId, string $userId, string $status): void
    {
        TemplateReviewer::query()
            ->where('template_id', $templateId)
            ->where('user_id', $userId)
            ->update(['status' => $status]);
    }

    /**
     * Returns true when NO reviewer has status != 'approved' (i.e. all approved).
     */
    public function allReviewersApproved(string $templateId): bool
    {
        return ! TemplateReviewer::query()
            ->where('template_id', $templateId)
            ->where('status', '!=', 'approved')
            ->exists();
    }

    /**
     * Force-deletes (physical delete) all reviewer records for a template, including soft-deleted.
     * Then inserts the given reviewer rows.
     *
     * @param  list<array{user_id: string, stage: int, status: string}>  $reviewers
     */
    public function replaceTemplateReviewers(string $templateId, array $reviewers): void
    {
        TemplateReviewer::withTrashed()
            ->where('template_id', $templateId)
            ->forceDelete();

        foreach ($reviewers as $row) {
            TemplateReviewer::query()->create(array_merge(
                ['template_id' => $templateId],
                $row,
            ));
        }
    }

    /**
     * Deletes all document reviewers for the template and inserts the given rows.
     *
     * @param  list<array{user_id: string, stage: int}>  $documentReviewers
     */
    public function replaceDocumentReviewers(string $templateId, array $documentReviewers): void
    {
        TemplateDocumentReviewer::query()
            ->where('template_id', $templateId)
            ->delete();

        foreach ($documentReviewers as $row) {
            TemplateDocumentReviewer::query()->create(array_merge(
                ['template_id' => $templateId],
                $row,
            ));
        }
    }

    /**
     * Copies template reviewers and document reviewers from $source to $target.
     * Uses 'pending' status for template reviewers.
     */
    public function copyReviewersFromTemplate(Template $source, Template $target): void
    {
        $source->loadMissing(['reviewers', 'documentReviewers']);

        $targetId = (string) $target->getKey();

        foreach ($source->reviewers as $reviewer) {
            TemplateReviewer::query()->create([
                'template_id' => $targetId,
                'user_id' => $reviewer->user_id,
                'stage' => $reviewer->stage,
                'status' => 'pending',
            ]);
        }

        foreach ($source->documentReviewers as $docReviewer) {
            TemplateDocumentReviewer::query()->create([
                'template_id' => $targetId,
                'user_id' => $docReviewer->user_id,
                'stage' => (int) $docReviewer->stage,
            ]);
        }
    }

    /**
     * Returns true if the template has blocks.
     */
    public function templateHasBlocks(string $templateId): bool
    {
        return TemplateBlock::query()
            ->where('template_id', $templateId)
            ->exists();
    }

    /**
     * Loads blocks relation (ordered by sort_order) on the given template model.
     * Returns the same model instance with blocks loaded.
     */
    public function loadBlocksForTemplate(Template $template): Template
    {
        $template->load(['blocks' => fn ($q) => $q->orderBy('sort_order')]);

        return $template;
    }

    /**
     * Loads blocks, reviewers and documentReviewers on the given template model for snapshot building.
     * Blocks ordered by sort_order; reviewers ordered by stage, user_id; documentReviewers by stage, user_id.
     * Returns the same model instance with all three relations loaded.
     */
    public function loadRelationsForSnapshot(Template $template): Template
    {
        $template->load([
            'blocks' => fn ($q) => $q->orderBy('sort_order'),
            'reviewers' => fn ($q) => $q->orderBy('stage')->orderBy('user_id'),
            'documentReviewers' => fn ($q) => $q->orderBy('stage')->orderBy('user_id'),
        ]);

        return $template;
    }

    /**
     * Bloques de la plantilla como payload DTOs ordenados por sort_order, para
     * construir snapshots e invariantes sin iterar la relación Eloquent.
     *
     * @return list<TemplateBlockPayloadDto>
     */
    public function blockPayloadSnapshot(string $templateId): array
    {
        return TemplateBlock::query()
            ->where('template_id', $templateId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (TemplateBlock $b) => TemplateBlockPayloadDto::fromModel($b))
            ->values()
            ->all();
    }
}
