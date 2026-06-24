<?php

namespace App\Events\Xero;

final readonly class XeroTokenRefreshed
{
    public int $connectionId;
    public string $tenantId;
    public int $expiresIn;

    public function __construct(
        int $connectionId,
        string $tenantId,
        int $expiresIn,
    ) {
        $this->connectionId = $connectionId;
        $this->tenantId = $tenantId;
        $this->expiresIn = $expiresIn;
    }
}
