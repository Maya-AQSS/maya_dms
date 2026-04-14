<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuditLogInsertLatencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requisito Técnico: La inserción en audit_log debe completarse
     * en menos de 10 ms para no añadir latencia perceptible al flujo principal.
     */
    public function test_audit_log_insert_completes_under_10ms(): void
    {
        $payload = [
            'entity_type'    => 'document',
            'entity_id'      => fake()->uuid(),
            'block_id'       => null,
            'action'         => 'created',
            'user_id'        => fake()->uuid(),
            'ip_address'     => '127.0.0.1',
            'user_agent'     => 'PHPUnit',
            'previous_value' => null,
            'new_value'      => json_encode(['title' => 'Test Document']),
        ];

        // Warm-up: primera inserción para descartar overhead de conexión/cache
        DB::table('audit_log')->insert(array_merge($payload, [
            'id'        => fake()->uuid(),
            'timestamp' => Carbon::now(),
        ]));

        // Medición real sobre N iteraciones
        $iterations = 50;
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            DB::table('audit_log')->insert([
                'id'             => fake()->uuid(),
                'entity_type'    => $payload['entity_type'],
                'entity_id'      => $payload['entity_id'],
                'block_id'       => $payload['block_id'],
                'action'         => $payload['action'],
                'user_id'        => $payload['user_id'],
                'ip_address'     => $payload['ip_address'],
                'user_agent'     => $payload['user_agent'],
                'timestamp'      => Carbon::now(),
                'previous_value' => $payload['previous_value'],
                'new_value'      => $payload['new_value'],
            ]);
        }

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $avgMs = $elapsedMs / $iterations;

        $this->assertLessThan(
            10.0,
            $avgMs,
            "La inserción promedio en audit_log tardó {$avgMs} ms (límite: 10 ms)."
        );
    }

    /**
     * Verifica que la latencia se mantiene estable incluso con volumen
     * previo de registros en la tabla (simula tabla con carga).
     */
    public function test_audit_log_insert_latency_stable_under_load(): void
    {
        // Sembrar 500 registros previos para simular tabla con datos
        $rows = [];
        for ($i = 0; $i < 500; $i++) {
            $rows[] = [
                'id'             => fake()->uuid(),
                'entity_type'    => fake()->randomElement(['document', 'template', 'comment']),
                'entity_id'      => fake()->uuid(),
                'block_id'       => fake()->optional(0.3)->uuid(),
                'action'         => fake()->randomElement(['created', 'updated', 'deleted', 'state_changed', 'approved', 'rejected']),
                'user_id'        => fake()->uuid(),
                'ip_address'     => fake()->ipv4(),
                'user_agent'     => fake()->userAgent(),
                'timestamp'      => now(),
                'previous_value' => null,
                'new_value'      => json_encode(['key' => 'value']),
            ];
        }

        // Insertar en lotes para no exceder límite de placeholders
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('audit_log')->insert($chunk);
        }

        // Medir inserción individual tras carga
        $iterations = 30;
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            DB::table('audit_log')->insert([
                'id'             => fake()->uuid(),
                'entity_type'    => 'document',
                'entity_id'      => fake()->uuid(),
                'block_id'       => null,
                'action'         => 'state_changed',
                'user_id'        => fake()->uuid(),
                'ip_address'     => '10.0.0.1',
                'user_agent'     => 'PHPUnit/Load',
                'timestamp'      => Carbon::now(),
                'previous_value' => json_encode(['status' => 'draft']),
                'new_value'      => json_encode(['status' => 'in_review']),
            ]);
        }

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $avgMs = $elapsedMs / $iterations;

        $this->assertLessThan(
            10.0,
            $avgMs,
            "Con 500+ registros previos, la inserción promedio tardó {$avgMs} ms (límite: 10 ms)."
        );
    }
}
