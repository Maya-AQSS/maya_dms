<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\DocumentReviewApproved;
use App\Events\DocumentStateChanged;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\JwtUser;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateDocumentReviewer;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\DocumentReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Trazabilidad de auditoría del flujo de revisión de documentos (alineado con plantillas):
 * - Aprobación intermedia → DocumentReviewApproved (única traza de la decisión del validador).
 * - Rechazo → DocumentStateChanged(rejected) enriquecido con etapa, validador y motivo.
 *
 * Requiere PostgreSQL (las migraciones usan gen_random_uuid()).
 */
class DocumentReviewAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Nombres deterministas para el enriquecimiento de auditoría.
        $this->mock(UserDirectoryRepositoryInterface::class)
            ->shouldReceive('findNameById')
            ->andReturnUsing(static fn (string $id): string => 'Nombre '.$id);
    }

    public function test_intermediate_approval_dispatches_review_approved_with_reviewer_name(): void
    {
        $creatorId = 'creator-doc-rev-01';
        $reviewerA = 'reviewer-doc-rev-a1';
        $reviewerB = 'reviewer-doc-rev-b2';

        $documentId = $this->seedInReviewDocument($creatorId, [
            ['user_id' => $reviewerA, 'stage' => 1],
            ['user_id' => $reviewerB, 'stage' => 2],
        ]);

        $reviewIdA = $this->reviewIdFor($documentId, $reviewerA);

        Event::fake([DocumentReviewApproved::class, DocumentStateChanged::class]);

        $this->actingAs($this->jwtUser($reviewerA));
        app(DocumentReviewService::class)->approveReview($documentId, $reviewIdA, $reviewerA);

        Event::assertDispatched(
            DocumentReviewApproved::class,
            function (DocumentReviewApproved $event) use ($documentId, $reviewerA): bool {
                $payload = $event->toAuditPayload();

                return $event->documentId === $documentId
                    && $event->actorId === $reviewerA
                    && $payload['action'] === 'review_approved'
                    && $payload['newValue']['stage'] === 1
                    && $payload['newValue']['status'] === 'approved'
                    && $payload['newValue']['reviewer_name'] === 'Nombre '.$reviewerA
                    && ! array_key_exists('review_id', $payload['newValue']);
            },
        );

        // Aprobación intermedia: el documento sigue in_review, no debe haber publicación.
        Event::assertNotDispatched(
            DocumentStateChanged::class,
            static fn (DocumentStateChanged $event): bool => $event->newStatus === 'published',
        );
    }

    public function test_reject_dispatches_state_changed_enriched_with_reviewer_and_reason(): void
    {
        $creatorId = 'creator-doc-rev-02';
        $reviewerId = 'reviewer-doc-rev-rej';

        $documentId = $this->seedInReviewDocument($creatorId, [
            ['user_id' => $reviewerId, 'stage' => 1],
        ]);

        $reviewId = $this->reviewIdFor($documentId, $reviewerId);

        Event::fake([DocumentStateChanged::class]);

        $this->actingAs($this->jwtUser($reviewerId));
        app(DocumentReviewService::class)->rejectReview(
            $documentId,
            $reviewId,
            $reviewerId,
            'Faltan datos en el apartado 2.',
        );

        Event::assertDispatched(
            DocumentStateChanged::class,
            function (DocumentStateChanged $event) use ($documentId, $reviewerId): bool {
                $payload = $event->toAuditPayload();

                return (string) $event->document->id === $documentId
                    && $event->actorId === $reviewerId
                    && $payload['action'] === 'state_changed'
                    && $payload['newValue']['status'] === 'rejected'
                    && $payload['newValue']['stage'] === 1
                    && $payload['newValue']['reviewer_name'] === 'Nombre '.$reviewerId
                    && $payload['newValue']['rejection_reason'] === 'Faltan datos en el apartado 2.';
            },
        );
    }

    private function jwtUser(string $id): JwtUser
    {
        return new JwtUser(['id' => $id, 'sub' => $id, 'permissions' => ['dms.login']]);
    }

    private function reviewIdFor(string $documentId, string $reviewerId): string
    {
        return (string) DB::table('document_reviews')
            ->where('document_id', $documentId)
            ->where('reviewer_id', $reviewerId)
            ->value('id');
    }

    /**
     * Siembra un documento y lo envía a revisión para generar las document_reviews pendientes.
     *
     * @param  list<array{user_id: string, stage: int}>  $reviewers
     */
    private function seedInReviewDocument(string $creatorId, array $reviewers): string
    {
        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla auditoría revisión',
            'description' => null,
            'visibility_level' => 'personal',
            'study_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => count($reviewers),
            'review_mode' => 'sequential',
        ]);

        $templateBlockId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id' => $templateBlockId,
            'template_id' => $templateId,
            'title' => 'Bloque',
            'default_content' => ['text' => 'Contenido inicial'],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        foreach ($reviewers as $reviewer) {
            TemplateDocumentReviewer::query()->forceCreate([
                'template_id' => $templateId,
                'user_id' => $reviewer['user_id'],
                'stage' => $reviewer['stage'],
            ]);
        }

        Document::query()->forceCreate([
            'id' => $documentId,
            'template_id' => $templateId,
            'title' => 'Documento auditoría revisión',
            'study_id' => null,
            'created_by' => $creatorId,
            'owner_id' => $creatorId,
            'status' => 'draft',
        ]);

        DocumentBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'template_block_id' => $templateBlockId,
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Contenido rellenado por el titular']]],
            ],
            'is_filled' => true,
            'sort_order' => 0,
            'last_edited_by' => $creatorId,
        ]);

        // El titular envía a validación (genera las document_reviews pendientes).
        $this->actingAs($this->jwtUser($creatorId));
        app(DocumentServiceInterface::class)->submitToReview(
            $documentId,
            $creatorId,
            'Envío a validación con changelog obligatorio.',
        );

        return $documentId;
    }
}
