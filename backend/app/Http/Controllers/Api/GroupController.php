<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\GroupServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function __construct(
        private readonly GroupServiceInterface $groupService,
    ) {}

    /**
     * Listar grupos.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    /**
     * Crear grupo.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Mostrar grupo.
     */
    public function show(string $id): JsonResponse
    {
        $group = $this->groupService->findOrFail($id);

        return response()->json(['data' => $group]);
    }

    /**
     * Actualizar grupo.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->groupService->findOrFail($id);

        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Eliminar grupo.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->groupService->findOrFail($id);

        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Agregar miembro al grupo.
     */
    public function addMember(Request $request, string $group): JsonResponse
    {
        $this->groupService->findOrFail($group);

        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Remover miembro del grupo.
     */
    public function removeMember(Request $request, string $group, string $userId): JsonResponse
    {
        $this->groupService->findOrFail($group);

        return response()->json(['message' => 'Not implemented'], 501);
    }
}
