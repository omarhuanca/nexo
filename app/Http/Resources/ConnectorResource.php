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
            'callback_url'    => $this->callback_url,
            'last_used_at'    => $this->last_used_at?->toISOString(),
            // El token en claro solo se expone una vez, en la respuesta de creación
            'token'           => $this->when($this->wasRecentlyCreated, $this->resource->plainToken),
            // El secreto del callback solo se expone al generarse o rotarse
            'callback_secret' => $this->when($this->resource->plainCallbackSecret !== null, $this->resource->plainCallbackSecret),
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
