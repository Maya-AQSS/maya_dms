<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Themes\UploadThemeAssetRequest;
use App\Http\Resources\ThemeResource;
use App\Services\Contracts\ThemeAssetServiceInterface;

class ThemeAssetController extends Controller
{
    public function __construct(
        private readonly ThemeAssetServiceInterface $service,
    ) {}

    /**
     * POST /api/v1/themes/{theme}/assets
     * Multipart form: kind=logo|background|watermark, file=<image>.
     */
    public function store(UploadThemeAssetRequest $request, string $theme): ThemeResource
    {
        $dto = $this->service->upload(
            themeId: $theme,
            assetKey: $request->fileColumn(),
            file: $request->file('file'),
        );

        return new ThemeResource($dto);
    }
}
