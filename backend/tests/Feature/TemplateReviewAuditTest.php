<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\TemplateReviewApproved;
use App\Events\TemplateStateChanged;
use App\Models\JwtUser;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateDocumentReviewer;
use App\Models\TemplateReviewer;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\TemplateReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Auditoría del flujo de revisión de plantillas (diseño "una fila por hecho"):
 *  - aprobación intermedia (sigue in_review) → TemplateReviewApproved
 *  - rechazo (→ rejected) y aprobación final (→ published) → TemplateStateChanged
 *    enriquecido con la etapa y el nombre del validador.
 *
 * Requiere PostgreSQL (igual que el resto de tests con RefreshDatabase de este suite).
 */
class TemplateReviewAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_intermediate_approval_dispatches_review_approved(): void
    {
        $this->fakeReviewerName('Validador Uno');
        Event::fake([TemplateReviewApproved::class]);

        $creatorId = 'creator-tpl-rev-01';
        $reviewerA = 'reviewer-tpl-rev-a';
        $reviewerB = 'reviewer-tpl-rev-b';
        // Dos revisores en paralelo: al aprobar uno, la plantilla sigue in_review (no
        // hay cambio de estado), por lo que la decisión solo se traza con este evento.
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
                    && $payload['newValue']['reviewer_name'] === 'Validador Uno';
            },
        );
    }

    public function test_reject_emits_state_changed_enriched_with_reviewer(): void
    {
        $this->fakeReviewerName('Validador Dos');
        Event::fake([TemplateStateChanged::class]);

        $creatorId = 'creator-tpl-rev-02';
        $reviewerId = 'reviewer-tpl-rev-c';
        $templateId = $this->seedInReviewTemplate($creatorId, [$reviewerId]);

        $this->actingAs(new JwtUser(['id' => $reviewerId, 'sub' => $reviewerId, 'permissions' => ['dms.login']]));

        app(TemplateReviewService::class)->rejectReview($templateId, $reviewerId);

        Event::assertDispatched(
            TemplateStateChanged::class,
            function (TemplateStateChanged $event) use ($templateId, $reviewerId): bool {
                if ($event->newStatus !== 'rejected') {
                    return false;
                }

                $payload = $event->toAuditPayload();

                return (string) $event->template->id === $templateId
                    && $event->actorId === $reviewerId
                    && $event->reviewerStage === 1
                    && $event->reviewerName === 'Validador Dos'
                    && $payload['action'] === 'state_changed'
                    && $payload['userId'] === $reviewerId
                    && $payload['previousValue']['status'] === 'in_review'
                    && $payload['newValue']['status'] === 'rejected'
                    && $payload['newValue']['stage'] === 1
                    && $payload['newValue']['reviewer_name'] === 'Validador Dos';
            },
        );
    }

    public function test_final_approval_publishes_with_state_changed_enriched(): void
    {
        $this->fakeReviewerName('Validador Tres');
        Event::fake([TemplateStateChanged::class]);

        $creatorId = 'creator-tpl-rev-03';
        $reviewerId = 'reviewer-tpl-rev-d';
        $templateId = $this->seedGlobalDraftTemplate($creatorId, [$reviewerId]);

        // Flujo real: el autor envía a validar (fija changelog y pasa a in_review) y el
        // único revisor aprueba → aprobación final → publicación.
        $this->actingAs(new JwtUser(['id' => $creatorId, 'sub' => $creatorId, 'permissions' => ['dms.login']]));
        app(TemplateReviewService::class)->submitForReview($templateId, $creatorId, 'Envío a validación con changelog obligatorio.');

        $this->actingAs(new JwtUser(['id' => $reviewerId, 'sub' => $reviewerId, 'permissions' => ['dms.login']]));
        app(TemplateReviewService::class)->approveReview($templateId, $reviewerId);

        Event::assertDispatched(
            TemplateStateChanged::class,
            function (TemplateStateChanged $event) use ($templateId, $reviewerId): bool {
                if ($event->newStatus !== 'published') {
                    return false;
                }

                $payload = $event->toAuditPayload();

                return (string) $event->template->id === $templateId
                    && $event->actorId === $reviewerId
                    && $event->reviewerStage === 1
                    && $event->reviewerName === 'Validador Tres'
                    && $payload['newValue']['status'] === 'published'
                    && $payload['newValue']['stage'] === 1
                    && $payload['newValue']['reviewer_name'] === 'Validador Tres';
            },
        );
    }

    private function fakeReviewerName(string $name): void
    {
        $mock = Mockery::mock(UserDirectoryRepositoryInterface::class)->shouldIgnoreMissing();
        $mock->shouldReceive('findNameById')->andReturn($name);
        $this->instance(UserDirectoryRepositoryInterface::class, $mock);
    }

    /**
     * Plantilla personal en revisión (las personales no requieren validador de documento).
     *
     * @param  list<string>  $reviewerIds
     */
    private function seedInReviewTemplate(string $creatorId, array $reviewerIds): string
    {
        return $this->seedTemplate($creatorId, $reviewerIds, 'in_review', 'personal', false);
    }

    /**
     * Plantilla global en borrador: usada para el flujo submit→approve→publish, porque
     * tras publicar el revisor sigue viéndola por la rama de catálogo publicado (una
     * personal dejaría de ser visible para el revisor y rompería findOrFail al limpiar
     * el snapshot de submission). Al ser no-personal, requiere validador de documento.
     *
     * @param  list<string>  $reviewerIds
     */
    private function seedGlobalDraftTemplate(string $creatorId, array $reviewerIds): string
    {
        return $this->seedTemplate($creatorId, $reviewerIds, 'draft', 'global', true);
    }

    /**
     * @param  list<string>  $reviewerIds
     */
    private function seedTemplate(
        string $creatorId,
        array $reviewerIds,
        string $status,
        string $visibility,
        bool $withDocumentReviewer,
    ): string {
        $templateId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla auditoría revisión',
            'description' => null,
            'visibility_level' => $visibility,
            'study_id' => null,
            'created_by' => $creatorId,
            'status' => $status,
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

        foreach ($reviewerIds as $reviewerId) {
            TemplateReviewer::query()->forceCreate([
                'template_id' => $templateId,
                'user_id' => $reviewerId,
                'stage' => 1,
                'status' => 'pending',
            ]);
        }

        if ($withDocumentReviewer) {
            TemplateDocumentReviewer::query()->forceCreate([
                'template_id' => $templateId,
                'user_id' => 'doc-reviewer-tpl-rev',
                'stage' => 1,
            ]);
        }

        return $templateId;
    }
}
