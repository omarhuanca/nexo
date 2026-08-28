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
        $vsdcPayload = $this->buildPayload($sale);
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

    public function buildPayload(Sale $sale): array
    {
        $payload = $sale->getPayload();

        $taxCorePayload = [
            'invoiceType' => $payload['invoiceType'],
            'transactionType' => $payload['transactionType'],
            'cashier' => $payload['cashier'] ?? null,
            'buyerId' => $sale->buyer?->document_number,
            'items' => $sale->lineItems->map(
                fn($item) => array_filter([
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unitPrice' => $item->unit_price,
                    'totalAmount' => $item->total_amount,
                    'labels' => $item->labels,
                    'gtin' => $item->gtin,
                ], fn($v) => $v !== null)
            )->toArray(),
            'payment' => $sale->payments->map(
                fn($p) => [
                    'amount' => $p->amount,
                    'paymentType' => $p->payment_type,
                ]
            )->toArray(),
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
