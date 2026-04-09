<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Groups\CreateGroupDto;
use App\DTOs\Groups\UpdateGroupDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groups\AddGroupMembersRequest;
use App\Http\Requests\Groups\StoreGroupRequest;
use App\Http\Requests\Groups\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Services\Contracts\GroupServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GroupController extends Controller
{
    public function __construct(
        private readonly GroupServiceInterface $groupService,
    ) {}

    /**
     * Listar grupos (con miembros; eager load en servicio/repositorio).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Group::class);

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $paginator = $this->groupService->paginateWithMembers($perPage);

        return GroupResource::collection($paginator);
    }

    /**
     * Crear un nuevo grupo.
     */
    public function store(StoreGroupRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $dto = new CreateGroupDto(
            name: $validated['name'],
            description: $validated['description'] ?? null,
        );

        $group = $this->groupService->create($dto);

        return (new GroupResource($group))->response()->setStatusCode(201);
    }

    /**
     * Localiza un grupo por su ID o lanza ModelNotFoundException.
     */
    public function show(string $id): GroupResource
    {
        $group = $this->groupService->findOrFail($id);
        $this->authorize('view', $group);
        $group->loadMissing('members');

        return new GroupResource($group);
    }

    /**
     * Actualiza un grupo.
     */
    public function update(UpdateGroupRequest $request, string $group): GroupResource
    {
        $validated = $request->validated();
        $dto = new UpdateGroupDto(
            name: $validated['name'] ?? null,
            description: $request->has('description') ? ($validated['description'] ?? null) : null,
            setDescription: $request->has('description'),
        );

        $updated = $this->groupService->update($group, $dto);

        return new GroupResource($updated);
    }

    /**
     * Elimina un grupo.
     */
    public function destroy(string $group): JsonResponse
    {
        $model = $this->groupService->findOrFail($group);
        $this->authorize('delete', $model);
        $this->groupService->delete($group);

        return response()->json(null, 204);
    }

    /**
     * Agrega miembros a un grupo.
     */
    public function addMember(AddGroupMembersRequest $request, string $group): JsonResponse
    {
        $validated = $request->validated();
        $ids = [];
        if (! empty($validated['user_id'])) {
            $ids[] = $validated['user_id'];
        }
        if (! empty($validated['user_ids'])) {
            $ids = array_merge($ids, $validated['user_ids']);
        }
        $role = $validated['role'] ?? 'member';

        $this->groupService->addMembers($group, array_values(array_unique($ids)), $role);

        return response()->json(['message' => 'OK'], 200);
    }

    /**
     * Elimina un miembro de un grupo.
     */
    public function removeMember(Request $request, string $group, string $userId): JsonResponse
    {
        $model = $this->groupService->findOrFail($group);
        $this->authorize('manageMembers', $model);
        $this->groupService->removeMember($group, $userId);

        return response()->json(null, 204);
    }
}
