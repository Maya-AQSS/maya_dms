<?php

namespace App\Http\Resources;

use App\Services\Contracts\TeamReadServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    /**
     * Convierte la plantilla en un array para la respuesta JSON.
     * 
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $team = $this->resolveTeam($request);

        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'description'        => $this->description,
            'visibility_level'   => $this->visibility_level->value,
            'delivery_deadline'  => $this->delivery_deadline?->toIso8601String(),
            'study_type_id'      => $this->study_type_id,
            'study_id'           => $this->study_id,
            'module_id'          => $this->module_id,
            'group_id'           => $this->group_id,
            'team'               => $team,
            'organization_id'    => $this->organization_id,
            'created_by'         => $this->created_by,
            'status'             => $this->status,
            'version'            => $this->version,
            'review_stages'      => $this->review_stages,
            'review_mode'        => $this->review_mode,
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private function resolveTeam(Request $request): ?array
    {
        if ($this->group_id === null) {
            return null;
        }

        $userId = (string) ($request->user()?->getAuthIdentifier() ?? '');
        if ($userId === '') {
            return null;
        }

        /** @var TeamReadServiceInterface $teamReadService */
        $teamReadService = app(TeamReadServiceInterface::class);

        return $teamReadService->findVisibleTeamByIdForUser($userId, (string) $this->group_id);
    }
}
