<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

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
        $page = $this->service->list($request->filters(), $request->perPage());

        return ThemeResource::collection($page);
    }

    public function show(string $theme): ThemeResource
    {
        $this->authorize('view', Theme::query()->findOrFail($theme));

        return new ThemeResource($this->service->get($theme));
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

    public function destroy(string $theme): Response
    {
        $model = Theme::query()->findOrFail($theme);
        $this->authorize('delete', $model);

        $this->service->delete($theme);

        return response()->noContent();
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
