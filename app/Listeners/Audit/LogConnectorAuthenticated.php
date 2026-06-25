<?php

namespace App\Listeners\Audit;

use App\Events\Auth\ConnectorAuthenticated;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogConnectorAuthenticated
{
    private LoggerService $logger;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function handle(ConnectorAuthenticated $event): void
    {
        $this->logger->info('Connector authenticated successfully', [
            'event' => 'auth.connector.success',
            'connector_id' => $event->connectorId,
            'organization_id' => $event->organizationId,
            'ip' => $event->ip,
            'user_agent' => $event->userAgent,
        ]);
    }
}
