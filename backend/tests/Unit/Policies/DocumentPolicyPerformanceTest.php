<?php

namespace Tests\Unit\Policies;

use App\Models\Document;
use App\Models\JwtUser;
use App\Policies\DocumentPolicy;
use Tests\TestCase;

/**
 * Requisito técnico F-01.3: verificación SoD sin consultas a BD (comparación de IDs en memoria).
 */
class DocumentPolicyPerformanceTest extends TestCase
{
    public function test_review_check_is_fast_enough_for_in_memory_id_comparison(): void
    {
        $policy = new DocumentPolicy;
        $user   = new JwtUser([
            'id'            => 'user-perf-1',
            'email'         => null,
            'name'          => null,
            'department'    => null,
            'permissions'   => [],
            'scope'         => '',
        ]);
        $doc = new Document;
        $doc->forceFill([
            'created_by' => 'other-user',
            'owner_id'   => 'other-user',
            'status'     => 'draft',
        ]);

        $iterations = 2_000;
        $start      = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $policy->review($user, $doc);
        }
        $avgMs = ((hrtime(true) - $start) / 1_000_000) / $iterations;

        $this->assertLessThan(
            1.0,
            $avgMs,
            "La media por llamada fue {$avgMs} ms (límite: 1 ms)."
        );
    }
}
