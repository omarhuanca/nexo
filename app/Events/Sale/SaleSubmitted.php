<?php

namespace App\Events\Sale;

final readonly class SaleSubmitted
{
    public int $saleId;
    public int $organizationId;
    public int $connectorId;
    public array $payload;

    public function __construct(
        int $saleId,
        int $organizationId,
        int $connectorId,
        array $payload,
    ) {
        $this->saleId = $saleId;
        $this->organizationId = $organizationId;
        $this->connectorId = $connectorId;
        $this->payload = $payload;
    }
}
