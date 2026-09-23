<?php

namespace App\Modules\Sale\Service;

use App\Jobs\SendConnectorCallbackJob;
use App\Modules\Connector\Domain\Connector;
use App\Modules\Sale\Domain\Sale;
use Illuminate\Support\Str;

/**
 * Notifies the connector that submitted a sale about each status change,
 * via a signed POST to the connector's callback_url.
 */
class SaleCallbackService
{
    public const EVENT_PROCESSING = 'sale.processing';
    public const EVENT_XERO_INVOICE_CREATED = 'sale.xero_invoice_created';
    public const EVENT_PENDING_FISCAL = 'sale.pending_fiscal';
    public const EVENT_COMPLETED = 'sale.completed';
    public const EVENT_XERO_FISCAL_SYNCED = 'sale.xero_fiscal_synced';
    public const EVENT_XERO_FISCAL_SYNC_FAILED = 'sale.xero_fiscal_sync_failed';
    public const EVENT_FAILED = 'sale.failed';

    public function notify(Sale $sale, string $event): void
    {
        $connector = $sale->connector ?? Connector::find($sale->getConnectorId());

        if (!$connector || !$connector->hasCallback()) return;

        $fiscal = is_array($sale->getFiscalResult()) ? $sale->getFiscalResult() : [];

        $body = [
            'id' => (string) Str::uuid(),
            'event' => $event,
            'occurred_at' => now()->toISOString(),
            'data' => [
                'sale_id' => $sale->getId(),
                'status' => $sale->getStatus(),
                'attempts' => $sale->getAttempts(),
                'xero_invoice_id' => $sale->getXeroInvoiceId(),
                'xero_invoice_number' => $sale->getXeroResult()['Invoices'][0]['InvoiceNumber'] ?? null,
                'fiscal_number' => $sale->getFiscalNumber(),
                'verification_url' => $fiscal['verificationUrl'] ?? null,
                'sdc_date_time' => $fiscal['sdcDateTime'] ?? null,
                'error_message' => $sale->getErrorMessage(),
            ],
        ];

        SendConnectorCallbackJob::dispatch($connector->getId(), $body);
    }
}
