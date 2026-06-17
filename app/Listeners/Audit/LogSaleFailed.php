<?php

namespace App\Listeners\Audit;

use App\Events\Sale\SaleFailed;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogSaleFailed
{
    public function __construct(private LoggerService $logger) {}

    public function handle(SaleFailed $event): void
    {
        $this->logger->error('Sale processing failed', [
            'event' => 'sale.failed',
            'sale_id' => $event->saleId,
            'organization_id' => $event->organizationId,
            'connector_id' => $event->connectorId,
            'attempt' => $event->attempt,
            'error_message' => $event->errorMessage,
        ]);
    }
}
