<?php

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
     * Árbol de jerarquía académica, con caché Redis y filtrado por usuario.
     */
    public function index(Request $request): JsonResponse
    {
        $tree = $this->hierarchyService->getCachedTree();

        $jwtProfile = $request->attributes->get('jwt_user');
        if (!$jwtProfile || !isset($jwtProfile['id'])) {
            return response()->json(['data' => []]);
        }

        $userId = $jwtProfile['id'];
        $profile = $this->profileService->getProfile($userId, $jwtProfile);

        $allowedStudyTypeIds = $profile['study_type_ids'] ?? [];
        $allowedStudyIds = $profile['study_ids'] ?? [];
        $allowedModuleIds = $profile['module_ids'] ?? [];
        $permissions = $profile['permissions'] ?? [];

        // Si es admin, auditor o tiene permiso de búsqueda global, mostramos todo.
        // También si no tiene ninguna restricción académica explícita y es un usuario con perfil de gestión.
        $isGlobalUser = in_array('admin', $permissions, true) 
            || in_array('users.search', $permissions, true) 
            || in_array('audit.read', $permissions, true);

        if (in_array('*', $allowedStudyTypeIds, true) || $isGlobalUser) {
            return response()->json(['data' => $tree]);
        }

        // Si no es global y no tiene asignaciones, no ve nada
        if (empty($allowedStudyTypeIds)) {
            return response()->json(['data' => []]);
        }

        $filteredTree = [];
        foreach ($tree as $studyType) {
            if (in_array((string)$studyType['id'], $allowedStudyTypeIds, true)) {
                $studies = [];
                foreach ($studyType['studies'] ?? [] as $study) {
                    if (empty($allowedStudyIds) || in_array((string)$study['id'], $allowedStudyIds, true)) {
                        $modules = [];
                        foreach ($study['course_modules'] ?? [] as $module) {
                            if (empty($allowedModuleIds) || in_array((string)$module['id'], $allowedModuleIds, true)) {
                                $modules[] = $module;
                            }
                        }
                        $study['course_modules'] = $modules;
                        $studies[] = $study;
                    }
                }
                $studyType['studies'] = $studies;
                $filteredTree[] = $studyType;
            }
        }

        return response()->json(['data' => $filteredTree]);
    }
}
