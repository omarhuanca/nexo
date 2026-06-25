<?php

namespace App\Listeners\Audit;

use App\Events\Auth\ConnectorAuthFailed;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogConnectorAuthFailed
{
    private LoggerService $logger;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function handle(ConnectorAuthFailed $event): void
    {
        $this->logger->warning('Connector authentication failed', [
            'event' => 'auth.connector.failed',
            'reason' => $event->reason,
            'token_prefix' => $event->tokenPrefix,
            'ip' => $event->ip,
            'user_agent' => $event->userAgent,
        ]);
    }
}
