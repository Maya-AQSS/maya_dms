<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Documents\ReviewerPoolDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pool de validadores efectivo del documento (wizard de envío a validación).
 *
 * @property ReviewerPoolDto $resource
 */
class ReviewerPoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ReviewerPoolDto $dto */
        $dto = $this->resource;

        return [
            'kind' => $dto->kind,
            'review_mode' => $dto->reviewMode,
            'reviewers' => ReviewerCandidateResource::collection($dto->reviewers)->resolve($request),
        ];
    }
}
