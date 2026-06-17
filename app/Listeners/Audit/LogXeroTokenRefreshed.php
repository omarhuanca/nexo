<?php

namespace App\Listeners\Audit;

use App\Events\Xero\XeroTokenRefreshed;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogXeroTokenRefreshed
{
    public function __construct(private LoggerService $logger) {}

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
