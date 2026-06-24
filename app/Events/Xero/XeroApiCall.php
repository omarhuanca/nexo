<?php

namespace App\Events\Xero;

final readonly class XeroApiCall
{
    public string $method;
    public string $endpoint;
    public int $statusCode;
    public int $durationMs;
    public ?string $tenantId;

    public function __construct(
        string $method,
        string $endpoint,
        int $statusCode,
        int $durationMs,
        ?string $tenantId = null,
    ) {
        $this->method = $method;
        $this->endpoint = $endpoint;
        $this->statusCode = $statusCode;
        $this->durationMs = $durationMs;
        $this->tenantId = $tenantId;
    }
}
