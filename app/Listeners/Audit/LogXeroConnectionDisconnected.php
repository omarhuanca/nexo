<?php

namespace App\Listeners\Audit;

use App\Events\Xero\XeroConnectionDisconnected;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogXeroConnectionDisconnected
{
    private LoggerService $logger;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function handle(XeroConnectionDisconnected $event): void
    {
        $this->logger->info('Xero connection disconnected', [
            'event' => 'xero.connection.disconnected',
            'organization_id' => $event->organizationId,
            'tenant_id' => $event->tenantId,
            'tenant_name' => $event->tenantName,
        ]);
    }
}
