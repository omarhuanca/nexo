<?php

namespace App\Events\Xero;

final readonly class XeroConnectionReplaced
{
    public int $organizationId;
    public string $oldTenantId;
    public string $newTenantId;

    public function __construct(
        int $organizationId,
        string $oldTenantId,
        string $newTenantId,
    ) {
        $this->organizationId = $organizationId;
        $this->oldTenantId = $oldTenantId;
        $this->newTenantId = $newTenantId;
    }
}
