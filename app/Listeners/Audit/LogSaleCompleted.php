<?php

namespace App\Listeners\Audit;

use App\Events\Sale\SaleCompleted;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogSaleCompleted
{
    public function __construct(private LoggerService $logger) {}

    public function handle(SaleCompleted $event): void
    {
        $this->logger->info('Sale completed successfully', [
            'event' => 'sale.completed',
            'sale_id' => $event->saleId,
            'organization_id' => $event->organizationId,
            'connector_id' => $event->connectorId,
            'xero_invoice_id' => $event->xeroInvoiceId,
            'fiscal_number' => $event->fiscalNumber,
            'duration_ms' => $event->durationMs,
        ]);
    }
}
