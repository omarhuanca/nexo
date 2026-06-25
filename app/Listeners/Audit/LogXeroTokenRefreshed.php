<?php

namespace App\Listeners\Audit;

use App\Events\Xero\XeroTokenRefreshed;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogXeroTokenRefreshed
{
    private LoggerService $logger;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function handle(XeroTokenRefreshed $event): void
    {
        $this->logger->info('Xero access token refreshed', [
            'event' => 'xero.token.refreshed',
            'connection_id' => $event->connectionId,
            'tenant_id' => $event->tenantId,
            'expires_in' => $event->expiresIn,
        ]);
    }
}
