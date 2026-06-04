<?php

namespace App\Jobs;

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
use Throwable;

class ProcessSaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $sale = $saleRepository->findByIdWithLock($this->saleId);

        if (!$sale || $sale->getStatus() === 'completed') {
            return;
        }

        $sale->setStatus('processing');
        $sale->setAttempts($sale->getAttempts() + 1);
        $saleRepository->save($sale);

        $payload        = $sale->getPayload();
        $organizationId = $sale->getOrganizationId();


        if ($sale->getXeroInvoiceId() === null) {
            $xeroConnection = $xeroConnectionService->findActiveByOrganization($organizationId);
            $xeroResult     = $xeroInvoiceSaleService->createInvoice($xeroConnection, $payload);

            $sale->setXeroInvoiceId($xeroResult['Invoices'][0]['InvoiceID'] ?? null);
            $sale->setXeroResult($xeroResult);
            $saleRepository->save($sale);
        }

        if ($sale->getFiscalNumber() === null) {
            $taxCoreConnection = $taxCoreConnectionService->findActiveByOrganization($organizationId);
            $fiscalResult      = $taxCoreSaleService->signInvoice($taxCoreConnection, $payload);

            $sale->setFiscalNumber($fiscalResult['invoiceNumber'] ?? null);
            $sale->setFiscalResult($fiscalResult);
            $saleRepository->save($sale);
        }

        $sale->setStatus('completed');
        $sale->setProcessedAt(now());
        $sale->setErrorMessage(null);
        $saleRepository->save($sale);
    }


    public function failed(Throwable $exception): void
    {
        /** @var SaleRepository $saleRepository */
        $saleRepository = app(SaleRepository::class);
        $sale = $saleRepository->findById($this->saleId);

        $sale->setStatus('failed');
        $sale->setErrorMessage($exception->getMessage());
        $saleRepository->save($sale);
    }
}
