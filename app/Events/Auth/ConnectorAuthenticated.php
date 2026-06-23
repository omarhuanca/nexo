<?php

namespace App\Events\Auth;

final readonly class ConnectorAuthenticated
{
    public int $connectorId;
    public int $organizationId;
    public string $ip;
    public string $userAgent;

    public function __construct(
        int $connectorId,
        int $organizationId,
        string $ip,
        string $userAgent,
    ) {
        $this->connectorId = $connectorId;
        $this->organizationId = $organizationId;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
    }
}
