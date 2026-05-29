<?php

namespace App\Jobs;

use App\Modules\Integration\TaxCore\Service\TaxCoreConnectionService;
use App\Modules\Integration\TaxCore\Service\TaxCoreSaleService;
use App\Modules\Integration\Xero\Service\XeroConnectionService;
use App\Modules\Integration\Xero\Service\XeroInvoiceSaleService;
use App\Modules\Sale\Domain\Sale;
use App\Modules\Sale\Repository\SaleRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessSaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retry up to 3 times with exponential backoff (seconds).
     * If Xero fails on all 3 attempts the job moves to failed_jobs
     * and failed() marks the sale as failed — TaxCore is never called.
     */
    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(private readonly int $saleId) {}

    public function handle(
        SaleRepository $saleRepository,
        XeroConnectionService $xeroConnectionService,
        XeroInvoiceSaleService $xeroInvoiceSaleService,
        TaxCoreConnectionService $taxCoreConnectionService,
        TaxCoreSaleService $taxCoreSaleService,
    ): void {
        // Pessimistic lock: prevents concurrent workers from processing the same sale.
        $sale = Sale::lockForUpdate()->find($this->saleId);

        if (!$sale || $sale->getStatus() === 'completed') {
            return;
        }

        $sale->setStatus('processing');
        $sale->setAttempts($sale->getAttempts() + 1);
        $sale->save();

        $payload        = $sale->getPayload();
        $organizationId = $sale->getOrganizationId();

        // ── Step 1: Xero Invoice (ACCREC) ────────────────────────────────────
        // Idempotent: skip if already completed on a previous attempt.
        if ($sale->getXeroInvoiceId() === null) {
            $xeroConnection = $xeroConnectionService->findActiveByOrganization($organizationId);
            $xeroResult     = $xeroInvoiceSaleService->createInvoice($xeroConnection, $payload);

            $sale->setXeroInvoiceId($xeroResult['Invoices'][0]['InvoiceID'] ?? null);
            $sale->setXeroResult($xeroResult);
            $sale->save();
            // Any exception thrown here will trigger a retry.
            // After all retries are exhausted, failed() is called and
            // the sale is marked as failed without ever reaching TaxCore.
        }

        // ── Step 2: TaxCore Fiscal Signing ───────────────────────────────────
        // Only reached when Xero succeeded. Also idempotent on retry.
        if ($sale->getFiscalNumber() === null) {
            $taxCoreConnection = $taxCoreConnectionService->findActiveByOrganization($organizationId);
            $fiscalResult      = $taxCoreSaleService->signInvoice($taxCoreConnection, $payload);

            $sale->setFiscalNumber($fiscalResult['invoiceNumber'] ?? null);
            $sale->setFiscalResult($fiscalResult);
            $sale->save();
        }

        $sale->setStatus('completed');
        $sale->setProcessedAt(now());
        $sale->setErrorMessage(null);
        $sale->save();
    }

    /**
     * Called by Laravel when all retry attempts are exhausted.
     * Marks the sale as failed and stores the last error message.
     */
    public function failed(Throwable $exception): void
    {
        $sale = Sale::find($this->saleId);

        if ($sale) {
            $sale->setStatus('failed');
            $sale->setErrorMessage($exception->getMessage());
            $sale->save();
        }
    }
}
