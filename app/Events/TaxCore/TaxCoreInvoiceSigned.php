<?php

namespace App\Events\TaxCore;

final readonly class TaxCoreInvoiceSigned
{
    public function __construct(
        public int $saleId,
        public string $invoiceNumber,
        public int $durationMs,
    ) {}
}
