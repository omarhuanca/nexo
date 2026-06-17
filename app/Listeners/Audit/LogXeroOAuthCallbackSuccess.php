<?php

namespace App\Listeners\Audit;

use App\Events\Xero\XeroOAuthCallbackSuccess;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogXeroOAuthCallbackSuccess
{
    public function __construct(private LoggerService $logger) {}

    public function handle(XeroOAuthCallbackSuccess $event): void
    {
        $this->logger->info('Xero OAuth callback succeeded', [
            'event' => 'xero.oauth.callback.success',
            'organization_id' => $event->organizationId,
            'tenant_id' => $event->tenantId,
            'tenant_name' => $event->tenantName,
        ]);
    }
}
