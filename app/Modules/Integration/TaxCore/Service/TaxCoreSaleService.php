<?php

namespace App\Modules\Integration\TaxCore\Service;

use App\Events\FiscalizationRequested;
use App\Modules\Sale\Domain\Sale;
use App\Modules\Sale\Repository\SaleRepository;
use Illuminate\Support\Str;
use Throwable;

class TaxCoreSaleService
{
    public function dispatchFiscalization(Sale $sale, SaleRepository $saleRepository): void
    {
        $vsdcPayload = $this->buildPayload($sale->getPayload());
        $taskId = (string) Str::uuid();

        $sale->setStatus('pending_fiscal');
        $saleRepository->save($sale);

        try {
            broadcast(new FiscalizationRequested(
                organizationId: $sale->getOrganizationId(),
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

    public function buildPayload(array $payload): array
    {
        $taxCorePayload = [
            'invoiceType' => $payload['invoiceType'],
            'transactionType' => $payload['transactionType'],
            'cashier' => $payload['cashier'] ?? null,
            'buyerId' => $payload['buyer']['id'] ?? null,
            'items' => array_map(
                fn(array $item) => array_filter([
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unitPrice' => $item['unitPrice'],
                    'totalAmount' => $item['totalAmount'],
                    'labels' => $item['labels'],
                    'gtin' => $item['gtin'] ?? null,
                ], fn($v) => $v !== null),
                $payload['items']
            ),
            'payment' => $payload['payment'],
        ];

        if (!empty($payload['referentDocumentNumber'])) {
            $taxCorePayload['referentDocumentNumber'] = $payload['referentDocumentNumber'];
        }

        if (!empty($payload['referentDocumentDT'])) {
            $taxCorePayload['referentDocumentDT'] = $payload['referentDocumentDT'];
        }

        return $taxCorePayload;
    }
}
