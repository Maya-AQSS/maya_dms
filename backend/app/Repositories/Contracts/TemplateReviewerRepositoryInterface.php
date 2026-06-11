<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Template;
use App\Models\TemplateReviewer;

interface TemplateReviewerRepositoryInterface
{
    /**
     * Returns IDs of templates (from the given list) where the user is a reviewer.
     * Result is a flipped map: ['template_id' => true].
     *
     * @param  list<string>  $templateIds
     * @return array<string, true>
     */
    public function findTemplateIdsWithReviewer(array $templateIds, string $userId): array;

    /**
     * Returns true if the user is a reviewer (active, non-deleted) on the given template.
     */
    public function existsReviewerForTemplate(string $templateId, string $userId): bool;

    /**
     * Finds a reviewer record for a template+user, or null if not found.
     */
    public function findReviewerForTemplate(string $templateId, string $userId): ?TemplateReviewer;

    /**
     * Updates the status of the reviewer identified by templateId + userId.
     */
    public function updateReviewerStatus(string $templateId, string $userId, string $status): void;

    /**
     * Returns true if all reviewers on the template have the given status != the excludedStatus.
     * Specifically: returns true when NO reviewer has status != 'approved' (i.e. all approved).
     */
    public function allReviewersApproved(string $templateId): bool;

    /**
     * Force-deletes (physical delete) all reviewer records for a template, including soft-deleted.
     * Then inserts the given reviewer rows.
     *
     * @param  list<array{user_id: string, stage: int, status: string}>  $reviewers
     */
    public function replaceTemplateReviewers(string $templateId, array $reviewers): void;

    /**
     * Deletes all document reviewers for the template and inserts the given rows.
     *
     * @param  list<array{user_id: string, stage: int}>  $documentReviewers
     */
    public function replaceDocumentReviewers(string $templateId, array $documentReviewers): void;

    /**
     * Copies template reviewers and document reviewers from $source to $target.
     * Uses 'pending' status for template reviewers.
     */
    public function copyReviewersFromTemplate(Template $source, Template $target): void;

    /**
     * Returns true if the template has blocks.
     */
    public function templateHasBlocks(string $templateId): bool;

    /**
     * Loads blocks relation (ordered by sort_order) on the given template model.
     * Returns the same model instance with blocks loaded.
     */
    public function loadBlocksForTemplate(Template $template): Template;

    /**
     * Loads blocks, reviewers and documentReviewers on the given template model for snapshot building.
     * Blocks ordered by sort_order; reviewers ordered by stage, user_id; documentReviewers by stage, user_id.
     * Returns the same model instance with all three relations loaded.
     */
    public function loadRelationsForSnapshot(Template $template): Template;
}
