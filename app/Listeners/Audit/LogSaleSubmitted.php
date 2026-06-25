<?php

namespace App\Listeners\Audit;

use App\Events\Sale\SaleSubmitted;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogSaleSubmitted
{
    private LoggerService $logger;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function handle(SaleSubmitted $event): void
    {
        $this->logger->info('Sale submitted for processing', [
            'event' => 'sale.submitted',
            'sale_id' => $event->saleId,
            'organization_id' => $event->organizationId,
            'connector_id' => $event->connectorId,
            'invoice_type' => $event->payload['invoiceType'] ?? null,
            'transaction_type' => $event->payload['transactionType'] ?? null,
            'items_count' => count($event->payload['items'] ?? []),
            'total_amount' => array_sum(array_column($event->payload['items'] ?? [], 'totalAmount')),
        ]);
    }
}
