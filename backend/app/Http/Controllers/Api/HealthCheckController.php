<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\HealthCheckServiceInterface;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    public function __construct(
        private readonly HealthCheckServiceInterface $healthCheckService,
    ) {}

    /**
     * Estado completo de todos los servicios dependientes.
     * HTTP 200 si todos ok, HTTP 503 si alguno falla.
     */
    public function index(): JsonResponse
    {
        $result = $this->healthCheckService->checkAll();
        $status = $result['status'] === 'ok' ? 200 : 503;

        return response()->json($result, $status);
    }

    /**
     * Liveness probe: confirma que el proceso Laravel está vivo.
     * No verifica dependencias externas. Siempre HTTP 200.
     */
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    /**
     * Readiness probe: verifica dependencias críticas (BD y Redis).
     * HTTP 200 cuando está listo, HTTP 503 mientras no lo esté.
     */
    public function ready(): JsonResponse
    {
        $result = $this->healthCheckService->checkReadiness();
        $status = $result['status'] === 'ok' ? 200 : 503;

        return response()->json($result, $status);
    }
}
