<?php

namespace App\Jobs;

use App\Events\FiscalizationRequested;
use App\Modules\Integration\Xero\Service\XeroConnectionService;
use App\Modules\Integration\Xero\Service\XeroInvoiceSaleService;
use App\Modules\Integration\TaxCore\Service\TaxCoreSaleService;
use App\Modules\Sale\Repository\SaleRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
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
        TaxCoreSaleService $taxCoreSaleService,
    ): void {
        $sale = $saleRepository->findByIdWithLock($this->saleId);

        if (!$sale || in_array($sale->getStatus(), ['completed', 'pending_fiscal'])) {
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
            $vsdcPayload = $taxCoreSaleService->buildPayload($payload);
            $taskId      = (string) Str::uuid();

            $sale->setStatus('pending_fiscal');
            $saleRepository->save($sale);

            try {
                broadcast(new FiscalizationRequested(
                    organizationId: $organizationId,
                    taskId: $taskId,
                    saleId: $sale->id,
                    method: 'POST',
                    endpoint: '/api/v3/invoices',
                    payload: $vsdcPayload,
                ));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $saleRepository = app(SaleRepository::class);
        $sale = $saleRepository->findById($this->saleId);

        $sale->setStatus('failed');
        $sale->setErrorMessage($exception->getMessage());
        $saleRepository->save($sale);
    }
}
