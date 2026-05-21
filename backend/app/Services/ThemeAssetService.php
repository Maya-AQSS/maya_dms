<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Themes\ThemeDto;
use App\DTOs\Themes\UpdateThemeDto;
use App\Repositories\Contracts\ThemeRepositoryInterface;
use App\Services\Contracts\ThemeAssetServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ThemeAssetService implements ThemeAssetServiceInterface
{
    private const DISK = 'themes';

    public function __construct(
        private readonly ThemeRepositoryInterface $repository,
    ) {}

    public function upload(string $themeId, string $assetKey, UploadedFile $file): ThemeDto
    {
        $theme = $this->repository->findById($themeId);
        if ($theme === null) {
            throw new NotFoundHttpException('Theme no encontrado.');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $filename = sprintf('%s-%s.%s', $assetKey, Str::uuid()->toString(), $ext);
        $path = $themeId.'/'.$filename;

        Storage::disk(self::DISK)->putFileAs($themeId, $file, $filename);

        // Eliminar el asset anterior del mismo tipo si existía (housekeeping).
        $previous = $theme->assets[$assetKey] ?? null;
        if (is_string($previous) && $previous !== '' && $previous !== $path) {
            Storage::disk(self::DISK)->delete($previous);
        }

        $newAssets = array_replace($theme->assets, [$assetKey => $path]);

        return $this->repository->update($themeId, new UpdateThemeDto(assets: $newAssets));
    }
}
