<?php

namespace App\Jobs;

use App\Modules\Integration\Xero\Service\XeroConnectionService;
use App\Modules\Integration\Xero\Service\XeroWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Xero requires the webhook receiver to respond within 5 seconds, or the
 * delivery is marked failed (and after repeated failures, the webhook is
 * disabled). This job carries the actual work — fetching the resource from
 * Xero, mapping it, and dispatching fiscalization — off the request cycle,
 * so XeroWebhookController::receive() can always return 200 immediately.
 */
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
