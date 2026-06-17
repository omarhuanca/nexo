<?php

namespace App\Events\Sale;

final readonly class SaleCompleted
{
    public function __construct(
        public int $saleId,
        public int $organizationId,
        public int $connectorId,
        public ?string $xeroInvoiceId,
        public ?string $fiscalNumber,
        public int $durationMs,
    ) {}
}
