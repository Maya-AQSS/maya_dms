<?php

namespace App\Services\Contracts;

interface HealthCheckServiceInterface
{
    /**
     * Ejecuta todos los checks (DB, Redis, FDW, RabbitMQ, WebSocket).
     * Devuelve status 'ok' si todos pasan, 'degraded' si alguno falla.
     */
    public function checkAll(): array;

    /**
     * Ejecuta solo los checks críticos (DB y Redis).
     * Usado por el readiness probe del orquestador.
     */
    public function checkReadiness(): array;
}
