<?php

namespace App\Events\TaxCore;

final readonly class TaxCoreApiCall
{
    public function __construct(
        public string $method,
        public string $endpoint,
        public int $statusCode,
        public int $durationMs,
        public ?int $connectionId = null,
    ) {}
}
