<?php

namespace App\Events\Auth;

final readonly class ConnectorAuthFailed
{
    public string $reason;
    public ?string $tokenPrefix;
    public string $ip;
    public string $userAgent;

    public function __construct(
        string $reason,
        ?string $tokenPrefix,
        string $ip,
        string $userAgent,
    ) {
        $this->reason = $reason;
        $this->tokenPrefix = $tokenPrefix;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
    }
}
