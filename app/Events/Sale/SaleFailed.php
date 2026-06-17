<?php

namespace App\Events\Sale;

final readonly class SaleFailed
{
    public function __construct(
        public int $saleId,
        public int $organizationId,
        public int $connectorId,
        public string $errorMessage,
        public int $attempt,
    ) {}
}
