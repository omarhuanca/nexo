<?php

namespace App\Events\Xero;

final readonly class XeroOAuthCallbackSuccess
{
    public int $organizationId;
    public string $tenantId;
    public string $tenantName;

    public function __construct(
        int $organizationId,
        string $tenantId,
        string $tenantName,
    ) {
        $this->organizationId = $organizationId;
        $this->tenantId = $tenantId;
        $this->tenantName = $tenantName;
    }
}
