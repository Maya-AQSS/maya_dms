<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Documents\ReviewerCandidateDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ReviewerCandidateDto $resource
 */
class ReviewerCandidateResource extends JsonResource
{
    /**
     * @return array{id: string, name: ?string, stage: ?int}
     */
    public function toArray(Request $request): array
    {
        /** @var ReviewerCandidateDto $dto */
        $dto = $this->resource;

        return [
            'id' => $dto->id,
            'name' => $dto->name,
            'stage' => $dto->stage,
        ];
    }
}
