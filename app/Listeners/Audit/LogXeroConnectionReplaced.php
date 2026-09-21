<?php

namespace App\Listeners\Audit;

use App\Events\Xero\XeroConnectionReplaced;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogXeroConnectionReplaced
{
    private LoggerService $logger;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function handle(XeroConnectionReplaced $event): void
    {
        $this->logger->info('Xero connection replaced with a different tenant', [
            'event' => 'xero.connection.replaced',
            'organization_id' => $event->organizationId,
            'old_tenant_id' => $event->oldTenantId,
            'new_tenant_id' => $event->newTenantId,
        ]);
    }
}
