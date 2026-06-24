<?php

namespace App\Events\TaxCore;

final readonly class TaxCoreCertUploaded
{
    public int $organizationId;
    public int $connectionId;
    public string $environment;
    public string $vsdcUrl;

    public function __construct(
        int $organizationId,
        int $connectionId,
        string $environment,
        string $vsdcUrl,
    ) {
        $this->organizationId = $organizationId;
        $this->connectionId = $connectionId;
        $this->environment = $environment;
        $this->vsdcUrl = $vsdcUrl;
    }
}
