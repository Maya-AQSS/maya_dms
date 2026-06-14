<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Themes\StoreThemeImageRequest;
use App\Services\Contracts\ThemeImageServiceInterface;
use App\Support\ThemeMediaUrl;
use Illuminate\Http\JsonResponse;

class ThemeImageController extends Controller
{
    public function __construct(
        private readonly ThemeImageServiceInterface $service,
    ) {}

    public function store(StoreThemeImageRequest $request, string $theme): JsonResponse
    {
        if ($request->hasFile('file')) {
            $result = $this->service->upload($theme, $request->file('file'));
        } else {
            $result = $this->service->ingestFromUrl($theme, $request->validated('url'));
        }

        return response()->json([
            'data' => [
                'src' => $result->src,
                'url' => ThemeMediaUrl::build($result->src),
            ],
        ], 201);
    }
}
