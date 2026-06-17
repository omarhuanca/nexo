<?php

namespace App\Jobs;

use App\Events\Sale\SaleCompleted;
use App\Events\Sale\SaleFailed;
use App\Events\Sale\SaleProcessingStarted;
use App\Modules\Integration\TaxCore\Service\TaxCoreConnectionService;
use App\Modules\Integration\TaxCore\Service\TaxCoreSaleService;
use App\Modules\Integration\Xero\Service\XeroConnectionService;
use App\Modules\Integration\Xero\Service\XeroInvoiceSaleService;
use App\Modules\Sale\Repository\SaleRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessSaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];
    public int $timeout = 120;

    private int $startedAt;

    public function __construct(private readonly int $saleId)
    {
        $this->startedAt = (int) (microtime(true) * 1000);
    }

    public function handle(
        SaleRepository $saleRepository,
        XeroConnectionService $xeroConnectionService,
        XeroInvoiceSaleService $xeroInvoiceSaleService,
        TaxCoreConnectionService $taxCoreConnectionService,
        TaxCoreSaleService $taxCoreSaleService,
    ): void {
        $sale = $saleRepository->findByIdWithLock($this->saleId);

        if (!$sale || $sale->getStatus() === 'completed') {
            return;
        }

        Log::shareContext([
            'sale_id' => $this->saleId,
            'organization_id' => $sale->getOrganizationId(),
            'connector_id' => $sale->getConnectorId(),
        ]);

        $attempt = $sale->getAttempts() + 1;
        $sale->setStatus('processing');
        $sale->setAttempts($attempt);
        $saleRepository->save($sale);

        event(new SaleProcessingStarted(
            $this->saleId,
            $sale->getOrganizationId(),
            $sale->getConnectorId(),
            $attempt,
        ));

        $payload        = $sale->getPayload();
        $organizationId = $sale->getOrganizationId();

        if ($sale->getXeroInvoiceId() === null) {
            $xeroConnection = $xeroConnectionService->findActiveByOrganization($organizationId);
            $xeroResult     = $xeroInvoiceSaleService->createInvoice($xeroConnection, $payload);

            $xeroInvoiceId = $xeroResult['Invoices'][0]['InvoiceID'] ?? null;
            $invoiceNumber = $xeroResult['Invoices'][0]['InvoiceNumber'] ?? null;

            $sale->setXeroInvoiceId($xeroInvoiceId);
            $sale->setXeroResult($xeroResult);
            $saleRepository->save($sale);

            if ($xeroInvoiceId) {
                event(new \App\Events\Xero\XeroInvoiceCreated(
                    $this->saleId,
                    $xeroInvoiceId,
                    (string) $invoiceNumber,
                ));
            }
        }

        if ($sale->getFiscalNumber() === null) {
            $taxCoreConnection = $taxCoreConnectionService->findActiveByOrganization($organizationId);
            $fiscalResult      = $taxCoreSaleService->signInvoice($taxCoreConnection, $payload);

            $fiscalNumber = $fiscalResult['invoiceNumber'] ?? null;

            $sale->setFiscalNumber($fiscalNumber);
            $sale->setFiscalResult($fiscalResult);
            $saleRepository->save($sale);

            if ($fiscalNumber) {
                $durationMs = (int) (microtime(true) * 1000) - $this->startedAt;
                event(new \App\Events\TaxCore\TaxCoreInvoiceSigned(
                    $this->saleId,
                    (string) $fiscalNumber,
                    $durationMs,
                ));
            }
        }

        $sale->setStatus('completed');
        $sale->setProcessedAt(now());
        $sale->setErrorMessage(null);
        $saleRepository->save($sale);

        $totalDuration = (int) (microtime(true) * 1000) - $this->startedAt;
        event(new SaleCompleted(
            $this->saleId,
            $sale->getOrganizationId(),
            $sale->getConnectorId(),
            $sale->getXeroInvoiceId(),
            $sale->getFiscalNumber(),
            $totalDuration,
        ));
    }


    public function failed(Throwable $exception): void
    {
        /** @var SaleRepository $saleRepository */
        $saleRepository = app(SaleRepository::class);
        $sale = $saleRepository->findById($this->saleId);

        if ($sale) {
            $sale->setStatus('failed');
            $sale->setErrorMessage($exception->getMessage());
            $saleRepository->save($sale);

            event(new SaleFailed(
                $this->saleId,
                $sale->getOrganizationId(),
                $sale->getConnectorId(),
                $exception->getMessage(),
                $sale->getAttempts(),
            ));
        }
    }
}
