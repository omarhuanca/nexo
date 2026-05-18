<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tax_id' => $this->tax_id,
            'active' => $this->active,
        ];
    }

    public function with($request): array
    {
        return [
            'meta' => [
                'resource_type' => 'organization',
                'version' => '1.0',
            ]
        ];
    }
}
