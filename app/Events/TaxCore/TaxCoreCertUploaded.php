<?php

namespace App\Events\TaxCore;

final readonly class TaxCoreCertUploaded
{
    public function __construct(
        public int $organizationId,
        public int $connectionId,
        public string $environment,
        public string $vsdcUrl,
    ) {}
}
