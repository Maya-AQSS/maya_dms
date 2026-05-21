<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Themes\UploadThemeAssetRequest;
use App\Http\Resources\ThemeResource;
use App\Models\Theme;
use App\Services\Contracts\ThemeAssetServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    /**
     * GET /api/v1/themes/{theme}/assets/{kind}
     * Sirve la imagen del asset. El frontend la consume con cookies de sesión
     * o el JWT; no se publica como URL pública. Si el theme no tiene ese
     * asset, devuelve 404.
     */
    public function show(Request $request, string $theme, string $kind): BinaryFileResponse
    {
        $model = Theme::query()->findOrFail($theme);
        $this->authorize('view', $model);

        $assetKey = match ($kind) {
            'logo' => 'logo_path',
            'background' => 'background_image_path',
            'watermark' => 'watermark_path',
            default => abort(404),
        };

        $path = (string) ($model->assets[$assetKey] ?? '');
        if ($path === '') {
            abort(404, 'Asset no configurado.');
        }

        $disk = Storage::disk('themes');
        if (! $disk->exists($path)) {
            abort(404, 'Asset no encontrado en disco.');
        }

        return response()->file($disk->path($path));
    }
}
