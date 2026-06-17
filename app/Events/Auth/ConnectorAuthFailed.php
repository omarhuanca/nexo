<?php

namespace App\Events\Auth;

final readonly class ConnectorAuthFailed
{
    public function __construct(
        public string $reason,
        public ?string $tokenPrefix,
        public string $ip,
        public string $userAgent,
    ) {}
}
