<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Devuelve el perfil del usuario autenticado desde los claims JWT.
     * No consulta base de datos — el perfil viene del caché Redis (JwtMiddleware).
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('jwt_user');

        return response()->json([
            'data' => $user,
        ]);
    }
}
