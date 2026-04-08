<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserProfileService $profileService,
    ) {}

    /**
     * Devuelve el perfil completo del usuario autenticado.
     *
     * Escenarios cubiertos:
     *   - Escenario 1: user_id se extrae del JWT (claim `sub`); la consulta siempre filtra por él.
     *   - Escenario 2: Perfil servido desde caché Redis si disponible.
     *   - Escenario 3: Si FDW no responde, retorna datos parciales del JWT.
     */
    public function me(Request $request): JsonResponse
    {
        $jwtProfile = $request->attributes->get('jwt_user');
        $userId     = $jwtProfile['id'];

        $profile = $this->profileService->getProfile($userId, $jwtProfile);

        return response()->json([
            'data' => $profile,
        ]);
    }
}
