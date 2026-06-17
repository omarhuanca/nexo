<?php

namespace App\Listeners\Audit;

use App\Events\Xero\XeroInvoiceCreated;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogXeroInvoiceCreated
{
    public function __construct(private LoggerService $logger) {}

    public function handle(XeroInvoiceCreated $event): void
    {
        $this->logger->info('Xero invoice created', [
            'event' => 'xero.invoice.created',
            'sale_id' => $event->saleId,
            'xero_invoice_id' => $event->xeroInvoiceId,
            'invoice_number' => $event->invoiceNumber,
        ]);
    }
}
