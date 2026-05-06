<?php

namespace Tests\Unit\Listeners;

use App\Listeners\RecordSegregationOfDutiesDenial;
use App\Models\Document;
use App\Models\JwtUser;
use Illuminate\Auth\Access\Events\GateEvaluated;
use Maya\Messaging\Publishers\AuditPublisher;
use Tests\TestCase;

class RecordSegregationOfDutiesDenialTest extends TestCase
{
    public function test_publishes_audit_event_when_document_submit_is_denied(): void
    {
        $user = new JwtUser([
            'id'          => 'not-owner',
            'email'       => null,
            'name'        => null,
            'department'  => null,
            'permissions' => [],
            'scope'       => '',
        ]);

        $document = new Document;
        $document->forceFill([
            'id'         => 'doc-uuid-1',
            'created_by' => 'owner-1',
            'owner_id'   => 'owner-1',
            'status'     => 'draft',
        ]);

        $publisher = $this->createMock(AuditPublisher::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(
                'maya-dms',
                'document',
                'doc-uuid-1',
                'sod_violation',
                'not-owner',
                null,
                null,
                $this->callback(fn (array $v) => ($v['ability'] ?? null) === 'submit'
                    && ($v['reason'] ?? null) === 'segregation_of_duties'),
                $this->anything(),
                $this->anything(),
            );

        $listener = new RecordSegregationOfDutiesDenial($publisher);
        $listener->handle(new GateEvaluated($user, 'submit', false, [$document]));
    }

    public function test_skips_when_gate_allows(): void
    {
        $publisher = $this->createMock(AuditPublisher::class);
        $publisher->expects($this->never())->method('publish');

        $user = new JwtUser([
            'id'          => 'reviewer',
            'email'       => null,
            'name'        => null,
            'department'  => null,
            'permissions' => [],
            'scope'       => '',
        ]);

        $document = new Document;
        $document->forceFill([
            'id'         => 'doc-1',
            'created_by' => 'other',
            'owner_id'   => 'other',
            'status'     => 'draft',
        ]);

        $listener = new RecordSegregationOfDutiesDenial($publisher);
        $listener->handle(new GateEvaluated($user, 'submit', true, [$document]));
    }
}
