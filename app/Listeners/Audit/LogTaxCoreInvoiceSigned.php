<?php

namespace App\Listeners\Audit;

use App\Events\TaxCore\TaxCoreInvoiceSigned;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogTaxCoreInvoiceSigned
{
    private LoggerService $logger;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function handle(TaxCoreInvoiceSigned $event): void
    {
        $this->logger->info('TaxCore invoice signed', [
            'event' => 'taxcore.invoice.signed',
            'sale_id' => $event->saleId,
            'invoice_number' => $event->invoiceNumber,
            'duration_ms' => $event->durationMs,
        ]);
    }
}
