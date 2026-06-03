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
    private const DISK = 'media';

    public function __construct(
        private readonly ThemeRepositoryInterface $repository,
    ) {}

    public function upload(string $themeId, string $assetKey, UploadedFile $file): ThemeDto
    {
        $assets = $this->repository->findThemeAssetsById($themeId);
        if ($assets === null) {
            throw new NotFoundHttpException('Theme no encontrado.');
        }

        $uuid = Str::uuid()->toString();
        $path = "themes/{$themeId}/{$uuid}";

        Storage::disk(self::DISK)->put($path, $file->getContent());

        // Delete previous asset of same kind if existed (housekeeping).
        $previous = $assets[$assetKey] ?? null;
        if (is_string($previous) && $previous !== '' && $previous !== $path) {
            Storage::disk(self::DISK)->delete($previous);
        }

        $newAssets = array_replace($assets, [$assetKey => $path]);

        return $this->repository->update($themeId, new UpdateThemeDto(assets: $newAssets));
    }
}
