<?php

namespace App\Events\Xero;

final readonly class XeroApiCall
{
    public function __construct(
        public string $method,
        public string $endpoint,
        public int $statusCode,
        public int $durationMs,
        public ?string $tenantId = null,
    ) {}
}
