<?php

namespace App\Listeners\Audit;

use App\Events\TaxCore\TaxCoreApiCall;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogTaxCoreApiCall
{
    public function __construct(private LoggerService $logger) {}

    public function handle(TaxCoreApiCall $event): void
    {
        $level = $event->statusCode >= 500 ? 'error' : ($event->statusCode >= 400 ? 'warning' : 'info');

        $this->logger->{$level}('TaxCore API call', [
            'event' => 'taxcore.api.call',
            'method' => $event->method,
            'endpoint' => $event->endpoint,
            'status_code' => $event->statusCode,
            'duration_ms' => $event->durationMs,
            'connection_id' => $event->connectionId,
        ]);
    }
}
