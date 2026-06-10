<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\TemplateSubmittedForReview;
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
class TemplateSubmitAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_for_review_dispatches_submitted_for_review_with_reviewers_and_mode(): void
    {
        Event::fake([TemplateSubmittedForReview::class]);

        $creatorId = 'creator-tpl-audit-01';
        $reviewerId = 'reviewer-tpl-audit-01';
        $templateId = $this->seedPersonalTemplateWithReviewer($creatorId, $reviewerId);

        // El repositorio aplica el scope global `user_access`: autenticamos al autor
        // para que su propia plantilla sea visible al resolverla en el servicio.
        $this->actingAs(new JwtUser(['id' => $creatorId, 'sub' => $creatorId, 'permissions' => ['dms.login']]));

        app(TemplateReviewService::class)->submitForReview(
            $templateId,
            $creatorId,
            'Envío a validación con changelog obligatorio.',
        );

        Event::assertDispatched(
            TemplateSubmittedForReview::class,
            function (TemplateSubmittedForReview $event) use ($templateId, $creatorId, $reviewerId): bool {
                $payload = $event->toAuditPayload();

                return $event->templateId === $templateId
                    && $event->actorId === $creatorId
                    && in_array($event->reviewMode, ['sequential', 'parallel'], true)
                    && count($event->reviewers) === 1
                    && $event->reviewers[0]['id'] === $reviewerId
                    && $event->reviewers[0]['stage'] === 1
                    && array_key_exists('name', $event->reviewers[0])
                    && $payload['action'] === 'submitted_for_review'
                    && $payload['entityType'] === 'template'
                    && $payload['newValue']['status'] === 'in_review'
                    && $payload['newValue']['review_mode'] === $event->reviewMode
                    && $payload['newValue']['reviewers'] === $event->reviewers;
            },
        );
    }

    private function seedPersonalTemplateWithReviewer(string $creatorId, string $reviewerId): string
    {
        $templateId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla auditoría submit',
            'description' => null,
            'visibility_level' => 'personal',
            'study_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
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

        // Plantilla personal con revisor: omite la auto-publicación y sigue la rama
        // in_review, que es la que emite TemplateSubmittedForReview.
        TemplateReviewer::query()->forceCreate([
            'template_id' => $templateId,
            'user_id' => $reviewerId,
            'stage' => 1,
            'status' => 'pending',
        ]);

        return $templateId;
    }
}
