<?php

namespace App\Events\Xero;

final readonly class XeroInvoiceCreated
{
    public function __construct(
        public int $saleId,
        public string $xeroInvoiceId,
        public string $invoiceNumber,
    ) {}
}
