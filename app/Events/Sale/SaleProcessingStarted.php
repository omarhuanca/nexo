<?php

namespace App\Events\Sale;

final readonly class SaleProcessingStarted
{
    public function __construct(
        public int $saleId,
        public int $organizationId,
        public int $connectorId,
        public int $attempt,
    ) {}
}
