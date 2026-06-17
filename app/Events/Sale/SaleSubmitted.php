<?php

namespace App\Events\Sale;

final readonly class SaleSubmitted
{
    public function __construct(
        public int $saleId,
        public int $organizationId,
        public int $connectorId,
        public array $payload,
    ) {}
}
