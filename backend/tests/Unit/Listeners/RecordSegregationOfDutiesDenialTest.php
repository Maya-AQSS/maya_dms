<?php

namespace Tests\Unit\Listeners;

use App\Listeners\RecordSegregationOfDutiesDenial;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\JwtUser;
use App\Services\Contracts\AuditLogServiceInterface;
use Illuminate\Auth\Access\Events\GateEvaluated;
use Tests\TestCase;

class RecordSegregationOfDutiesDenialTest extends TestCase
{
    public function test_persists_audit_when_document_submit_is_denied(): void
    {
        $user = new JwtUser([
            'id'              => 'user-1',
            'email'           => null,
            'name'            => null,
            'department'      => null,
            'organization_id' => null,
            'roles'           => [],
            'scope'           => '',
        ]);

        $document = new Document;
        $document->forceFill([
            'id'         => 'doc-uuid-1',
            'created_by' => 'user-1',
            'owner_id'   => 'user-1',
            'status'     => 'draft',
        ]);

        $audit = $this->createMock(AuditLogServiceInterface::class);
        $audit->expects($this->once())
            ->method('record')
            ->with(
                'document',
                'doc-uuid-1',
                'sod_violation',
                'user-1',
                null,
                null,
                $this->callback(fn (array $v) => ($v['ability'] ?? null) === 'submit'
                    && ($v['reason'] ?? null) === 'segregation_of_duties'),
                $this->anything(),
                $this->anything(),
            )
            ->willReturn(new AuditLog);

        $listener = new RecordSegregationOfDutiesDenial($audit);
        $listener->handle(new GateEvaluated($user, 'submit', false, [$document]));
    }

    public function test_skips_when_gate_allows(): void
    {
        $audit = $this->createMock(AuditLogServiceInterface::class);
        $audit->expects($this->never())->method('record');

        $user = new JwtUser([
            'id'              => 'reviewer',
            'email'           => null,
            'name'            => null,
            'department'      => null,
            'organization_id' => null,
            'roles'           => [],
            'scope'           => '',
        ]);

        $document = new Document;
        $document->forceFill([
            'id'         => 'doc-1',
            'created_by' => 'other',
            'owner_id'   => 'other',
            'status'     => 'draft',
        ]);

        $listener = new RecordSegregationOfDutiesDenial($audit);
        $listener->handle(new GateEvaluated($user, 'submit', true, [$document]));
    }
}
