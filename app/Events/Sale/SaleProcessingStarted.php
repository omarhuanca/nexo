<?php

namespace App\Events\Sale;

final readonly class SaleProcessingStarted
{
    public int $saleId;
    public int $organizationId;
    public int $connectorId;
    public int $attempt;

    public function __construct(
        int $saleId,
        int $organizationId,
        int $connectorId,
        int $attempt,
    ) {
        $this->saleId = $saleId;
        $this->organizationId = $organizationId;
        $this->connectorId = $connectorId;
        $this->attempt = $attempt;
    }
}
