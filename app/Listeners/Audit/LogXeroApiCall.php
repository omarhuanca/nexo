<?php

namespace App\Listeners\Audit;

use App\Events\Xero\XeroApiCall;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogXeroApiCall
{
    private LoggerService $logger;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function handle(XeroApiCall $event): void
    {
        $level = $event->statusCode >= 500 ? 'error' : ($event->statusCode >= 400 ? 'warning' : 'info');

        $this->logger->{$level}('Xero API call', [
            'event' => 'xero.api.call',
            'method' => $event->method,
            'endpoint' => $event->endpoint,
            'status_code' => $event->statusCode,
            'duration_ms' => $event->durationMs,
            'tenant_id' => $event->tenantId,
        ]);
    }
}
