<?php

namespace App\Listeners\Audit;

use App\Events\Sale\SaleProcessingStarted;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogSaleProcessingStarted
{
    private LoggerService $logger;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function handle(SaleProcessingStarted $event): void
    {
        $this->logger->info('Sale processing started', [
            'event' => 'sale.processing',
            'sale_id' => $event->saleId,
            'organization_id' => $event->organizationId,
            'connector_id' => $event->connectorId,
            'attempt' => $event->attempt,
        ]);
    }
}
