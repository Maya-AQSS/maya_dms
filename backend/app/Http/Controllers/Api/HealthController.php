<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserFdwService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(UserFdwService $fdwService): JsonResponse
    {
        $fdw = $fdwService->checkConnectivity();

        $allOk = $fdw['status'] === 'ok';

        return response()->json([
            'status'  => $allOk ? 'ok' : 'degraded',
            'service' => 'maya-dms',
            'version' => '1.0.0',
            'checks'  => [
                'fdw' => $fdw,
            ],
        ], $allOk ? 200 : 503);
    }
}
