<?php

namespace App\Events\Auth;

final readonly class ConnectorAuthenticated
{
    public function __construct(
        public int $connectorId,
        public int $organizationId,
        public string $ip,
        public string $userAgent,
    ) {}
}
