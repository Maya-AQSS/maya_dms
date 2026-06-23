<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\Templates\UpdateTemplateDto;
use App\Events\OwnershipTransferred;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Repositories\Contracts\DocumentBlockRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateBlockRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateReviewerRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionBlockLayerRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\EntityVersionDestroyService;
use App\Services\TemplatePublishingService;
use App\Services\TemplateReviewerAssignmentService;
use App\Services\TemplateReviewService;
use App\Services\TemplateService;
use App\Services\TemplateVersionBlockLayerResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Maya\Messaging\Publishers\NotificationPublisher;
use Mockery;
use Tests\TestCase;

final class TemplateServiceOwnershipTransferNotificationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_update_notifies_new_owner_when_created_by_changes(): void
    {
        Event::fake([OwnershipTransferred::class]);

        $templateId = '00000000-0000-0000-0000-000000000101';
        $previousOwner = '00000000-0000-0000-0000-000000000201';
        $newOwner = '00000000-0000-0000-0000-000000000202';
        $actorId = '00000000-0000-0000-0000-000000000203';
        $now = now();

        $headId = '00000000-0000-0000-0000-000000000301';
        $headVersion = new EntityVersion;
        $headVersion->forceFill([
            'id' => $headId,
            'snapshot_data' => [
                'template' => [
                    'id' => $templateId,
                    'name' => 'Plantilla FP',
                    'created_by' => $previousOwner,
                    'visibility_level' => 'personal',
                    'delivery_deadline' => $now->copy()->addWeek()->toIso8601String(),
                ],
            ],
            'created_by' => $previousOwner,
        ]);

        $template = new Template;
        $template->forceFill([
            'id' => $templateId,
            'head_entity_version_id' => $headId,
            'name' => 'Plantilla FP',
            'visibility_level' => 'personal',
            'delivery_deadline' => $now->copy()->addWeek(),
            'created_by' => $previousOwner,
        ]);
        $template->setRelation('headVersion', $headVersion);

        $updated = clone $template;
        $updated->forceFill(['created_by' => $newOwner]);
        $updatedHead = clone $headVersion;
        $updatedHead->forceFill([
            'snapshot_data' => [
                'template' => [
                    'id' => $templateId,
                    'name' => 'Plantilla FP',
                    'created_by' => $newOwner,
                    'visibility_level' => 'personal',
                    'delivery_deadline' => $now->copy()->addWeek()->toIso8601String(),
                ],
            ],
            'created_by' => $newOwner,
        ]);
        $updated->setRelation('headVersion', $updatedHead);

        $templateRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $templateRepo->shouldReceive('update')->once()->andReturn($updated);

        $userDirectory = Mockery::mock(UserDirectoryRepositoryInterface::class);
        // DMS-B11: la transferencia resuelve los 3 nombres en un solo findNamesByIds.
        $userDirectory->shouldReceive('findNamesByIds')->andReturn([
            $actorId => 'Coordinador',
            $previousOwner => 'Anterior',
            $newOwner => 'Nuevo',
        ]);

        $notificationPublisher = Mockery::mock(NotificationPublisher::class);
        $notificationPublisher->shouldReceive('send')
            ->once()
            ->withArgs(function (string $type, ?string $recipientId): bool {
                return $type === 'template.ownership_transferred'
                    && $recipientId === '00000000-0000-0000-0000-000000000202';
            });

        Auth::shouldReceive('id')->andReturn($actorId);

        $service = new TemplateService(
            $templateRepo,
            Mockery::mock(TemplateVersionRepositoryInterface::class),
            Mockery::mock(EntityVersionRepositoryInterface::class),
            Mockery::mock(TemplateBlockRepositoryInterface::class),
            Mockery::mock(TemplateReviewerRepositoryInterface::class),
            Mockery::mock(TemplatePublishingService::class),
            Mockery::mock(TemplateReviewService::class),
            Mockery::mock(TemplateReviewerAssignmentService::class),
            Mockery::mock(DocumentBlockRepositoryInterface::class),
            Mockery::mock(AcademicHierarchyRepositoryInterface::class),
            $userDirectory,
            // EntityVersionDestroyService y TemplateVersionBlockLayerResolver son
            // `final` (no mockeables): instancias reales con deps mockeadas — no se
            // ejercitan en el camino update→transferencia.
            new EntityVersionDestroyService(Mockery::mock(EntityVersionRepositoryInterface::class)),
            new TemplateVersionBlockLayerResolver(
                Mockery::mock(EntityVersionRepositoryInterface::class),
                Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class),
            ),
            $notificationPublisher,
        );

        $service->update($template, new UpdateTemplateDto(
            createdBy: $newOwner,
            setCreatedBy: true,
        ));

        Event::assertDispatched(OwnershipTransferred::class);
    }
}
