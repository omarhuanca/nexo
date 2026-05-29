<?php

namespace App\Modules\Integration\TaxCore\Service;

use App\Modules\Integration\TaxCore\Domain\TaxCoreConnection;

class TaxCoreSaleService
{
    public function __construct(private readonly TaxCoreApiService $apiService) {}

    public function signInvoice(TaxCoreConnection $connection, array $payload): array
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

        $response = $this->apiService->post($connection, '/api/v3/invoices', $taxCorePayload);

        return $response->json();
    }
}
