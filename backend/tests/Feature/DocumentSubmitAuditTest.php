<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\DocumentSubmittedForReview;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\JwtUser;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateDocumentReviewer;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Requiere PostgreSQL (igual que el resto de tests con RefreshDatabase de este suite):
 * las migraciones usan gen_random_uuid().
 */
class DocumentSubmitAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_to_review_dispatches_submitted_for_review_with_reviewers_and_mode(): void
    {
        Event::fake([DocumentSubmittedForReview::class]);

        $creatorId = 'creator-doc-audit-01';
        $reviewerId = 'reviewer-doc-audit-01';
        [$documentId] = $this->seedTemplateAndDocumentWithReviewer($creatorId, $reviewerId);

        // El repositorio aplica el scope global `user_access`: autenticamos al titular
        // para que su propio documento sea visible al resolverlo en el servicio.
        $this->actingAs(new JwtUser(['id' => $creatorId, 'sub' => $creatorId, 'permissions' => ['dms.login']]));

        app(DocumentServiceInterface::class)->submitToReview(
            $documentId,
            $creatorId,
            'Envío a validación con changelog obligatorio.',
        );

        Event::assertDispatched(
            DocumentSubmittedForReview::class,
            function (DocumentSubmittedForReview $event) use ($documentId, $creatorId, $reviewerId): bool {
                $payload = $event->toAuditPayload();

                return $event->documentId === $documentId
                    && $event->actorId === $creatorId
                    && in_array($event->reviewMode, ['sequential', 'parallel'], true)
                    && count($event->reviewers) === 1
                    && $event->reviewers[0]['id'] === $reviewerId
                    && $event->reviewers[0]['stage'] === 1
                    && array_key_exists('name', $event->reviewers[0])
                    && $payload['action'] === 'submitted_for_review'
                    && $payload['entityType'] === 'document'
                    && $payload['newValue']['status'] === 'in_review'
                    && $payload['newValue']['review_mode'] === $event->reviewMode
                    && $payload['newValue']['reviewers'] === $event->reviewers;
            },
        );
    }

    /**
     * @return array{0: string} [documentId]
     */
    private function seedTemplateAndDocumentWithReviewer(string $creatorId, string $reviewerId): array
    {
        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();

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

        $templateBlockId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id' => $templateBlockId,
            'template_id' => $templateId,
            'title' => 'Bloque',
            'default_content' => ['text' => 'Contenido inicial'],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        // Validador de documento por configuración live (sin versión anclada): hace que
        // submitToReview siga la rama in_review en lugar de auto-publicar.
        TemplateDocumentReviewer::query()->forceCreate([
            'template_id' => $templateId,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        Document::query()->forceCreate([
            'id' => $documentId,
            'template_id' => $templateId,
            'title' => 'Documento auditoría submit',
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

        return [$documentId];
    }
}
