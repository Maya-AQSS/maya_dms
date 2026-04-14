<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'entity_type'    => $this->entity_type,
            'entity_id'      => $this->entity_id,
            'block_id'       => $this->block_id,
            'action'         => $this->action,
            'user_id'        => $this->user_id,
            'ip_address'     => $this->ip_address,
            'user_agent'     => $this->user_agent,
            'timestamp'      => $this->timestamp,
            'previous_value' => $this->previous_value,
            'new_value'      => $this->new_value,
        ];
    }
}
