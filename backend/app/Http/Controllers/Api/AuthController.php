<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\UserProfileServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserProfileServiceInterface $profileService,
    ) {}

    /**
     * Devuelve el perfil del usuario autenticado (identidad, departamento, permisos, equipos).
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
