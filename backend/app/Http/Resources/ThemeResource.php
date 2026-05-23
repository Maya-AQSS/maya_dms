<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Themes\ThemeDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Recibe un ThemeDto desde ThemeService — nunca el modelo Eloquent.
 *
 * @property-read ThemeDto $resource
 */
class ThemeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ThemeDto $t */
        $t = $this->resource;

        return [
            'id' => $t->id,
            'name' => $t->name,
            'description' => $t->description,
            'status' => $t->status,
            'created_by' => $t->createdBy,
            'team_id' => $t->teamId,
            'palette' => $t->palette,
            'typography' => $t->typography,
            'layout' => $t->layout,
            'assets' => [
                'logo_path'             => $this->buildMediaUrl($t->assets['logo_path'] ?? null),
                'background_image_path' => $this->buildMediaUrl($t->assets['background_image_path'] ?? null),
                'watermark_path'        => $this->buildMediaUrl($t->assets['watermark_path'] ?? null),
            ],
            'accessibility' => $t->accessibility,
            'cloned_from_id' => $t->clonedFromId,
            'created_at' => $t->createdAt,
            'updated_at' => $t->updatedAt,
        ];
    }

    /**
     * Converts a stored path (themes/{themeId}/{uuid}) to an HMAC-signed
     * media URL readable by <img> and WeasyPrint without a Bearer token.
     * Returns null for missing paths or legacy-format paths that predate the
     * unified media endpoint.
     */
    private function buildMediaUrl(mixed $path): ?string
    {
        if (!is_string($path) || $path === '') {
            return null;
        }

        $uuid = basename($path);
        if (!Str::isUuid($uuid)) {
            return null; // Legacy path — asset must be re-uploaded.
        }

        $parts = explode('/', $path);
        $token = hash_hmac('sha256', $path, (string) config('app.key'));
        $base  = route('api.v1.media.show', ['uuid' => $uuid]);

        if (count($parts) >= 3) {
            $ct = rtrim($parts[0], 's'); // 'themes' → 'theme'
            $ci = $parts[1];
            return "{$base}?ct={$ct}&ci={$ci}&token={$token}";
        }

        return "{$base}?token={$token}";
    }
}
