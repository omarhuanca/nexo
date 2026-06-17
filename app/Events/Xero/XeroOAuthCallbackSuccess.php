<?php

namespace App\Events\Xero;

final readonly class XeroOAuthCallbackSuccess
{
    public function __construct(
        public int $organizationId,
        public string $tenantId,
        public string $tenantName,
    ) {}
}
