<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_event_id' => $this->external_event_id,
            'connector_id' => $this->connector_id,
            'organization_id' => $this->organization_id,
            'event_type' => $this->event_type,
            'payload' => $this->payload,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'received_at' => $this->received_at?->toISOString(),
            'processed_at' => $this->processed_at?->toISOString(),
            'error_message' => $this->error_message,
        ];
    }
}
