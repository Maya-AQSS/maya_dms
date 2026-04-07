<?php

namespace App\Services;

use App\Services\Contracts\HealthCheckServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckService implements HealthCheckServiceInterface
{
    /**
     * Timeout máximo por check TCP (RabbitMQ, WebSocket).
     * Con 2 servicios TCP caídos el peor caso es ~1s, dentro del límite de 2s.
     */
    private const TCP_TIMEOUT = 0.5;

    /**
     * Verifica si todos los servicios están operativos.
     */
    public function checkAll(): array
    {
        $checks = [
            'laravel'   => ['status' => 'ok', 'latency_ms' => 0],
            'database'  => $this->checkDatabase(),
            'redis'     => $this->checkRedis(),
            'fdw'       => $this->checkFdw(),
            'rabbitmq'  => $this->checkTcp(
                (string) env('RABBITMQ_HOST', 'localhost'),
                (int) env('RABBITMQ_PORT', 5672),
            ),
            'websocket' => $this->checkTcp(
                (string) env('REVERB_HOST', 'localhost'),
                (int) env('REVERB_PORT', 8082),
            ),
        ];

        $allOk = collect($checks)->every(fn ($c) => $c['status'] === 'ok');

        return [
            'status'   => $allOk ? 'ok' : 'degraded',
            'services' => $checks,
        ];
    }

    /**
     * Verifica si el servidor está listo para recibir tráfico.
     */
    public function checkReadiness(): array
    {
        $database = $this->checkDatabase();
        $redis    = $this->checkRedis();

        $ready = $database['status'] === 'ok' && $redis['status'] === 'ok';

        return [
            'status'   => $ready ? 'ok' : 'degraded',
            'services' => compact('database', 'redis'),
        ];
    }

    /**
     * Verifica si el servidor PostgreSQL está accesible.
     */
    private function checkDatabase(): array
    {
        try {
            $start = hrtime(true);
            DB::select('SELECT 1');
            return ['status' => 'ok', 'latency_ms' => $this->ms($start)];
        } catch (\Throwable) {
            return ['status' => 'down', 'latency_ms' => null];
        }
    }

    /**
     * Verifica si el servidor Redis está accesible.
     */
    private function checkRedis(): array
    {
        try {
            $start = hrtime(true);
            Redis::ping();
            return ['status' => 'ok', 'latency_ms' => $this->ms($start)];
        } catch (\Throwable) {
            return ['status' => 'down', 'latency_ms' => null];
        }
    }

    /**
     * Verifica la conectividad real con el servidor FDW ejecutando una consulta
     * sobre users_fdw. Retornará 'down' si la conexión falla o la tabla no existe.
     */
    private function checkFdw(): array
    {
        try {
            $start = hrtime(true);
            DB::select('SELECT 1 FROM users_fdw LIMIT 1');
            return ['status' => 'ok', 'latency_ms' => $this->ms($start)];
        } catch (\Throwable) {
            return ['status' => 'down', 'latency_ms' => null];
        }
    }

    /**
     * Verifica si el servidor TCP está accesible.
     */
    private function checkTcp(string $host, int $port): array
    {
        try {
            $start  = hrtime(true);
            $socket = @fsockopen($host, $port, $errno, $errstr, self::TCP_TIMEOUT);

            if ($socket === false) {
                return ['status' => 'down', 'latency_ms' => null];
            }

            fclose($socket);
            return ['status' => 'ok', 'latency_ms' => $this->ms($start)];
        } catch (\Throwable) {
            return ['status' => 'down', 'latency_ms' => null];
        }
    }

    /**
     * Convierte el tiempo medido en nanosegundos a milisegundos.
     */
    private function ms(int $start): int
    {
        return (int) round((hrtime(true) - $start) / 1_000_000);
    }
}
