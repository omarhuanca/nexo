<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The shape is shared by both the public listing (`GET /integrations/taxcore/invoices`)
     * and the public detail (`GET /integrations/taxcore/invoices/{id}`) endpoints.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fiscal = is_array($this->fiscal_result) ? $this->fiscal_result : [];

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'connector_id' => $this->connector_id,
            'status' => $this->status,
            'fiscal_number' => $this->fiscal_number,
            'xero_invoice_id' => $this->xero_invoice_id,
            'total_amount' => $fiscal['totalAmount'] ?? null,
            'sdc_date_time' => $fiscal['sdcDateTime'] ?? null,
            'attempts' => $this->attempts,
            'error_message' => $this->error_message,
            'processed_at' => $this->processed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'payload' => $this->payload,
            'fiscal_result' => $this->fiscal_result,
            'xero_result' => $this->xero_result,
        ];
    }
}
