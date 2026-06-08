<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Themes\ThemeDto;
use App\Support\ThemeMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'layout' => $this->withImageUrls($t->layout),
            'accessibility' => $t->accessibility,
            'cloned_from_id' => $t->clonedFromId,
            'created_at' => $t->createdAt,
            'updated_at' => $t->updatedAt,
        ];
    }

    /**
     * Inyecta srcUrl en los bloques imagen del layout sin mutar el original.
     * srcUrl es un campo derivado (solo lectura) que resuelve src a una URL firmada.
     *
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    private function withImageUrls(array $layout): array
    {
        if (empty($layout['regions']) || ! is_array($layout['regions'])) {
            return $layout;
        }

        $cleanRegions = array_map(function (array $region) {
            if (($region['type'] ?? null) !== 'image') {
                return $region;
            }
            if (empty($region['props']) || ! is_array($region['props'])) {
                return $region;
            }

            $src = $region['props']['src'] ?? null;
            if (! is_string($src) || $src === '') {
                return $region;
            }

            $url = ThemeMediaUrl::build($src);
            if ($url === null) {
                return $region;
            }

            return array_replace($region, [
                'props' => array_replace($region['props'], ['srcUrl' => $url]),
            ]);
        }, $layout['regions']);

        return array_replace($layout, ['regions' => $cleanRegions]);
    }
}
