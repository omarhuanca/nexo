<?php

namespace App\Events\Xero;

final readonly class XeroTokenRefreshed
{
    public function __construct(
        public int $connectionId,
        public string $tenantId,
        public int $expiresIn,
    ) {}
}
