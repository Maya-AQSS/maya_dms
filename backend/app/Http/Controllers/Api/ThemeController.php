<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Themes\ThemeDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Themes\CloneThemeRequest;
use App\Http\Requests\Themes\IndexThemeRequest;
use App\Http\Requests\Themes\StoreThemeRequest;
use App\Http\Requests\Themes\UpdateThemeRequest;
use App\Http\Resources\ThemeResource;
use App\Models\Theme;
use App\Services\Contracts\ThemeServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ThemeController extends Controller
{
    public function __construct(
        private readonly ThemeServiceInterface $service,
    ) {}

    public function index(IndexThemeRequest $request): AnonymousResourceCollection
    {
        $page = $this->service->list(
            $request->filters(),
            $request->perPage(),
            $request->getSortBy(),
            $request->getSortDir(),
        );

        return ThemeResource::collection($page);
    }

    public function show(string $theme): ThemeResource
    {
        $dto = $this->service->get($theme);
        $this->authorize('view', $this->modelForPolicy($dto));

        return new ThemeResource($dto);
    }

    public function store(StoreThemeRequest $request): JsonResponse
    {
        $userId = (string) $request->user()->getAuthIdentifier();
        $dto = $this->service->create($request->toCreateDto(), $userId);

        return (new ThemeResource($dto))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function update(UpdateThemeRequest $request, string $theme): ThemeResource
    {
        return new ThemeResource($this->service->update($theme, $request->toUpdateDto()));
    }

    public function publish(string $theme): ThemeResource
    {
        $dto = $this->service->get($theme);
        $this->authorize('update', $this->modelForPolicy($dto));

        return new ThemeResource($this->service->publish($theme));
    }

    public function archive(string $theme): ThemeResource
    {
        $dto = $this->service->get($theme);
        $this->authorize('update', $this->modelForPolicy($dto));

        return new ThemeResource($this->service->archive($theme));
    }

    public function destroy(string $theme): Response
    {
        $dto = $this->service->get($theme);
        $this->authorize('delete', $this->modelForPolicy($dto));

        $this->service->delete($theme);

        return response()->noContent();
    }

    /**
     * Modelo efímero (no persistido) para evaluar la ThemePolicy. Necesita
     * `created_by` y `status` además del `id`, porque la policy distingue al
     * creador y a los themes publicados — un stub solo con `id` rompía la
     * autorización de `view`/`delete`.
     */
    private function modelForPolicy(ThemeDto $dto): Theme
    {
        $model = new Theme;
        $model->id = $dto->id;
        $model->created_by = $dto->createdBy;
        $model->status = $dto->status;
        $model->is_system = $dto->isSystem;

        return $model;
    }

    public function clone(CloneThemeRequest $request, string $theme): JsonResponse
    {
        $userId = (string) $request->user()->getAuthIdentifier();
        $dto = $this->service->clone($theme, $userId, $request->toCloneDto());

        return (new ThemeResource($dto))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }
}
