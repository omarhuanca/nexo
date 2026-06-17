<?php

namespace App\Listeners\Audit;

use App\Events\TaxCore\TaxCoreCertUploaded;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogTaxCoreCertUploaded
{
    public function __construct(private LoggerService $logger) {}

    public function handle(TaxCoreCertUploaded $event): void
    {
        $this->logger->info('TaxCore certificate uploaded', [
            'event' => 'taxcore.cert.uploaded',
            'organization_id' => $event->organizationId,
            'connection_id' => $event->connectionId,
            'environment' => $event->environment,
            'vsdc_url' => $event->vsdcUrl,
        ]);
    }
}
