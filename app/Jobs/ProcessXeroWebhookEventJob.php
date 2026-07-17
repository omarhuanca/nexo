<?php

namespace App\Jobs;

use App\Modules\Integration\Xero\Service\XeroConnectionService;
use App\Modules\Integration\Xero\Service\XeroWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessXeroWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];
    public int $timeout = 60;

    public function __construct(private readonly array $event) {}

    public function handle(XeroWebhookService $webhookService, XeroConnectionService $connectionService): void
    {
        $data = $webhookService->getData(
            $this->event['eventCategory'],
            $this->event['resourceId'],
            $this->event['tenantId']
        );

        logger()->info($data);

        if ($this->event['eventCategory'] === 'INVOICE') {
            $connection = $connectionService->findByTenantId($this->event['tenantId']);
            $webhookService->handleInvoiceEvent($connection, $data);
        }
    }
}
