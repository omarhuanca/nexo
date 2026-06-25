<?php

namespace App\Events\Xero;

final readonly class XeroInvoiceCreated
{
    public int $saleId;
    public string $xeroInvoiceId;
    public string $invoiceNumber;

    public function __construct(
        int $saleId,
        string $xeroInvoiceId,
        string $invoiceNumber,
    ) {
        $this->saleId = $saleId;
        $this->xeroInvoiceId = $xeroInvoiceId;
        $this->invoiceNumber = $invoiceNumber;
    }
}
