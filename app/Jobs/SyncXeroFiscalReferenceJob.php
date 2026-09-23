<?php

namespace App\Jobs;

use App\Modules\Integration\Xero\Service\XeroConnectionService;
use App\Modules\Integration\Xero\Service\XeroInvoiceSaleService;
use App\Modules\Sale\Repository\SaleRepository;
use App\Modules\Sale\Service\SaleCallbackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * After TaxCore signs a sale, copies the fiscal number into the Xero invoice
 * Reference and attaches the verification URL. Runs once per sale.
 */
class SyncXeroFiscalReferenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 60, 300, 900];
    public int $timeout = 60;

    public function __construct(private readonly int $saleId) {}

    public function handle(
        SaleRepository $saleRepository,
        XeroConnectionService $xeroConnectionService,
        XeroInvoiceSaleService $xeroInvoiceSaleService,
        SaleCallbackService $saleCallbackService,
    ): void {
        $sale = $saleRepository->findById($this->saleId);

        if (!$sale || $sale->getXeroFiscalSyncedAt() !== null) return;
        if ($sale->getXeroInvoiceId() === null || $sale->getFiscalNumber() === null) return;

        $connection = $xeroConnectionService->findActiveByOrganization($sale->getOrganizationId());
        $xeroInvoiceSaleService->applyFiscalReference($connection, $sale);

        $sale->setXeroFiscalSyncedAt(now());
        $saleRepository->save($sale);

        $saleCallbackService->notify($sale, SaleCallbackService::EVENT_XERO_FISCAL_SYNCED);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Could not write fiscal reference to Xero invoice.', [
            'sale_id' => $this->saleId,
            'error' => $exception->getMessage(),
        ]);

        $sale = app(SaleRepository::class)->findById($this->saleId);

        if ($sale) {
            app(SaleCallbackService::class)->notify($sale, SaleCallbackService::EVENT_XERO_FISCAL_SYNC_FAILED);
        }
    }
}
