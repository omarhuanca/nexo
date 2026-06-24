<?php

namespace App\Events\TaxCore;

final readonly class TaxCoreInvoiceSigned
{
    public int $saleId;
    public string $invoiceNumber;
    public int $durationMs;

    public function __construct(
        int $saleId,
        string $invoiceNumber,
        int $durationMs,
    ) {
        $this->saleId = $saleId;
        $this->invoiceNumber = $invoiceNumber;
        $this->durationMs = $durationMs;
    }
}
