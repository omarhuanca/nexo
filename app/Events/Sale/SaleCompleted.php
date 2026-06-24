<?php

namespace App\Events\Sale;

final readonly class SaleCompleted
{
    public int $saleId;
    public int $organizationId;
    public int $connectorId;
    public ?string $xeroInvoiceId;
    public ?string $fiscalNumber;
    public int $durationMs;

    public function __construct(
        int $saleId,
        int $organizationId,
        int $connectorId,
        ?string $xeroInvoiceId,
        ?string $fiscalNumber,
        int $durationMs,
    ) {
        $this->saleId = $saleId;
        $this->organizationId = $organizationId;
        $this->connectorId = $connectorId;
        $this->xeroInvoiceId = $xeroInvoiceId;
        $this->fiscalNumber = $fiscalNumber;
        $this->durationMs = $durationMs;
    }
}
