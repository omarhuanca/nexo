<?php

namespace App\Listeners\Audit;

use App\Events\TaxCore\TaxCoreCertUploaded;
use App\Shared\Logging\LoggerService;
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
final readonly class LogTaxCoreCertUploaded
{
    private LoggerService $logger;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }

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
