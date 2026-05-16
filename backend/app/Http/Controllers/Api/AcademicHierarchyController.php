<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\AcademicHierarchyServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicHierarchyController extends Controller
{
    public function __construct(
        private readonly AcademicHierarchyServiceInterface $hierarchyService,
        private readonly UserProfileServiceInterface $profileService,
    ) {}

    /**
     * Árbol de jerarquía académica filtrado por el perfil del usuario.
     */
    public function index(Request $request): JsonResponse
    {
        $jwtProfile = $request->attributes->get('jwt_user');
        if (! is_array($jwtProfile) || ! isset($jwtProfile['id'])) {
            return response()->json(['data' => []]);
        }

        $profile = $this->profileService->getProfile((string) $jwtProfile['id'], $jwtProfile);

        return response()->json([
            'data' => $this->hierarchyService->getFilteredTreeForProfile($profile),
        ]);
    }
}
