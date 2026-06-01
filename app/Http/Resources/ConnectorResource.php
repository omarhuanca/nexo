<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'organization_id' => $this->organization_id,
            'name'            => $this->name,
            'active'          => $this->active,
            'allowed_events'  => $this->allowed_events,
            'last_used_at'    => $this->last_used_at?->toISOString(),
            // El token solo se expone en la respuesta de creación
            'token'           => $this->when($this->wasRecentlyCreated, $this->token),
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
