<?php

namespace App\Events\TaxCore;

final readonly class TaxCoreCertUploaded
{
    public int $organizationId;
    public int $connectionId;
    public string $environment;

    public function __construct(
        int $organizationId,
        int $connectionId,
        string $environment,
    ) {
        $this->organizationId = $organizationId;
        $this->connectionId = $connectionId;
        $this->environment = $environment;
    }
}
