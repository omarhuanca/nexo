<?php

namespace App\Events\Sale;

final readonly class SaleFailed
{
    public int $saleId;
    public int $organizationId;
    public int $connectorId;
    public string $errorMessage;
    public int $attempt;

    public function __construct(
        int $saleId,
        int $organizationId,
        int $connectorId,
        string $errorMessage,
        int $attempt,
    ) {
        $this->saleId = $saleId;
        $this->organizationId = $organizationId;
        $this->connectorId = $connectorId;
        $this->errorMessage = $errorMessage;
        $this->attempt = $attempt;
    }
}
