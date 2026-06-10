<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\TemplateReviewApproved;
use App\Events\TemplateReviewRejected;
use App\Models\JwtUser;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateReviewer;
use App\Services\TemplateReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Requiere PostgreSQL (igual que el resto de tests con RefreshDatabase de este suite):
 * las migraciones usan gen_random_uuid().
 */
class TemplateReviewAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_review_dispatches_review_approved(): void
    {
        Event::fake([TemplateReviewApproved::class]);

        $creatorId = 'creator-tpl-rev-01';
        $reviewerA = 'reviewer-tpl-rev-a';
        $reviewerB = 'reviewer-tpl-rev-b';
        // Dos revisores en paralelo: al aprobar uno, no se cumple "todos aprobaron",
        // así que se evita la rama de auto-publicación y la prueba queda acotada.
        $templateId = $this->seedInReviewTemplate($creatorId, [$reviewerA, $reviewerB]);

        $this->actingAs(new JwtUser(['id' => $reviewerA, 'sub' => $reviewerA, 'permissions' => ['dms.login']]));

        app(TemplateReviewService::class)->approveReview($templateId, $reviewerA);

        Event::assertDispatched(
            TemplateReviewApproved::class,
            function (TemplateReviewApproved $event) use ($templateId, $reviewerA): bool {
                $payload = $event->toAuditPayload();

                return $event->templateId === $templateId
                    && $event->actorId === $reviewerA
                    && (string) $event->reviewer->user_id === $reviewerA
                    && $payload['action'] === 'review_approved'
                    && $payload['entityType'] === 'template'
                    && $payload['userId'] === $reviewerA
                    && $payload['newValue']['status'] === 'approved'
                    && $payload['previousValue']['status'] === 'pending'
                    && $payload['newValue']['stage'] === 1
                    && array_key_exists('reviewer_name', $payload['newValue'])
                    && ! array_key_exists('review_id', $payload['newValue']);
            },
        );
    }

    public function test_reject_review_dispatches_review_rejected(): void
    {
        Event::fake([TemplateReviewRejected::class]);

        $creatorId = 'creator-tpl-rev-02';
        $reviewerId = 'reviewer-tpl-rev-c';
        $templateId = $this->seedInReviewTemplate($creatorId, [$reviewerId]);

        $this->actingAs(new JwtUser(['id' => $reviewerId, 'sub' => $reviewerId, 'permissions' => ['dms.login']]));

        app(TemplateReviewService::class)->rejectReview($templateId, $reviewerId);

        Event::assertDispatched(
            TemplateReviewRejected::class,
            function (TemplateReviewRejected $event) use ($templateId, $reviewerId): bool {
                $payload = $event->toAuditPayload();

                return $event->templateId === $templateId
                    && $event->actorId === $reviewerId
                    && (string) $event->reviewer->user_id === $reviewerId
                    && $payload['action'] === 'review_rejected'
                    && $payload['entityType'] === 'template'
                    && $payload['userId'] === $reviewerId
                    && $payload['newValue']['status'] === 'rejected'
                    && $payload['previousValue']['status'] === 'pending'
                    && array_key_exists('reviewer_name', $payload['newValue'])
                    && ! array_key_exists('review_id', $payload['newValue'])
                    && ! array_key_exists('rejection_reason', $payload['newValue']);
            },
        );
    }

    /**
     * @param  list<string>  $reviewerIds
     */
    private function seedInReviewTemplate(string $creatorId, array $reviewerIds): string
    {
        $templateId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla auditoría revisión',
            'description' => null,
            'visibility_level' => 'personal',
            'study_id' => null,
            'created_by' => $creatorId,
            'status' => 'in_review',
            'review_stages' => 0,
            'review_mode' => 'parallel',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $templateId,
            'title' => 'Bloque',
            'default_content' => ['text' => 'Contenido inicial'],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        foreach ($reviewerIds as $i => $reviewerId) {
            TemplateReviewer::query()->forceCreate([
                'template_id' => $templateId,
                'user_id' => $reviewerId,
                'stage' => 1,
                'status' => 'pending',
            ]);
            unset($i);
        }

        return $templateId;
    }
}
