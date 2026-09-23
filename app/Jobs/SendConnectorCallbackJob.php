<?php

namespace App\Jobs;

use App\Modules\Connector\Domain\Connector;
use App\Shared\Helpers\HttpClientHelper;
use App\Shared\Security\PublicHttpsUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Delivers one signed callback to a connector.
 *
 * Headers sent:
 *  - X-Nexo-Event: event name (e.g. sale.completed)
 *  - X-Nexo-Delivery: unique delivery id (same across retries, use it for idempotency)
 *  - X-Nexo-Timestamp: unix seconds when signed
 *  - X-Nexo-Signature: sha256=HMAC_SHA256(callback_secret, "{timestamp}.{raw body}")
 */
class SendConnectorCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;
    public array $backoff = [10, 60, 300, 900, 3600];
    public int $timeout = 30;

    public function __construct(
        private readonly int $connectorId,
        private readonly array $body,
    ) {}

    public function handle(): void
    {
        $connector = Connector::find($this->connectorId);

        if (!$connector || !$connector->hasCallback()) return;

        $url = $connector->getCallbackUrl();

        if (!PublicHttpsUrl::isAllowed($url)) {
            Log::warning('Connector callback skipped: URL resolves to a non-public address.', [
                'connector_id' => $this->connectorId,
                'event' => $this->body['event'],
            ]);
            return;
        }

        $raw = json_encode($this->body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$raw}", $connector->getCallbackSecret());

        $response = HttpClientHelper::http()
            ->timeout(10)
            ->withoutRedirecting()
            ->withHeaders([
                'X-Nexo-Event' => $this->body['event'],
                'X-Nexo-Delivery' => $this->body['id'],
                'X-Nexo-Timestamp' => $timestamp,
                'X-Nexo-Signature' => "sha256={$signature}",
                'User-Agent' => 'nexo-webhooks/1.0',
            ])
            ->withBody($raw, 'application/json')
            ->post($url);

        if (!$response->successful()) {
            throw new RuntimeException("Connector callback returned HTTP {$response->status()}");
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Connector callback delivery failed permanently.', [
            'connector_id' => $this->connectorId,
            'event' => $this->body['event'] ?? null,
            'delivery_id' => $this->body['id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
