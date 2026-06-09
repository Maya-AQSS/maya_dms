<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\SodViolationDetected;
use App\Listeners\RecordSegregationOfDutiesDenial;
use App\Models\Document;
use App\Models\JwtUser;
use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RecordSegregationOfDutiesDenialTest extends TestCase
{
    public function test_dispatches_sod_violation_event_when_document_submit_is_denied(): void
    {
        Event::fake([SodViolationDetected::class]);

        $user = new JwtUser([
            'id' => 'not-owner',
            'email' => null,
            'name' => null,
            'department' => null,
            'permissions' => [],
            'scope' => '',
        ]);

        $document = new Document;
        $document->forceFill([
            'id' => 'doc-uuid-1',
            'created_by' => 'owner-1',
            'owner_id' => 'owner-1',
            'status' => 'draft',
        ]);

        $listener = new RecordSegregationOfDutiesDenial;
        $listener->handle(new GateEvaluated($user, 'submit', false, [$document]));

        Event::assertDispatched(
            SodViolationDetected::class,
            function (SodViolationDetected $event): bool {
                $payload = $event->toAuditPayload();

                return $payload['applicationSlug'] === 'maya-dms'
                    && $payload['entityType'] === 'document'
                    && $payload['entityId'] === 'doc-uuid-1'
                    && $payload['action'] === 'sod_violation'
                    && $payload['userId'] === 'not-owner'
                    && ($payload['newValue']['ability'] ?? null) === 'submit'
                    && ($payload['newValue']['reason'] ?? null) === 'segregation_of_duties';
            },
        );
    }

    public function test_skips_when_gate_allows(): void
    {
        Event::fake([SodViolationDetected::class]);

        $user = new JwtUser([
            'id' => 'reviewer',
            'email' => null,
            'name' => null,
            'department' => null,
            'permissions' => [],
            'scope' => '',
        ]);

        $document = new Document;
        $document->forceFill([
            'id' => 'doc-1',
            'created_by' => 'other',
            'owner_id' => 'other',
            'status' => 'draft',
        ]);

        $listener = new RecordSegregationOfDutiesDenial;
        $listener->handle(new GateEvaluated($user, 'submit', true, [$document]));

        Event::assertNotDispatched(SodViolationDetected::class);
    }
}
