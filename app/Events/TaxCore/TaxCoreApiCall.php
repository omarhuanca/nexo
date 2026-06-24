<?php

namespace App\Events\TaxCore;

final readonly class TaxCoreApiCall
{
    public string $method;
    public string $endpoint;
    public int $statusCode;
    public int $durationMs;
    public int $connectionId;

    public function __construct(
        string $method,
        string $endpoint,
        int $statusCode,
        int $durationMs,
        int $connectionId,
    ) {
        $this->method = $method;
        $this->endpoint = $endpoint;
        $this->statusCode = $statusCode;
        $this->durationMs = $durationMs;
        $this->connectionId = $connectionId;
    }
}
