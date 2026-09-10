<?php

namespace App\Modules\Integration\Xero\Service;

use App\Modules\Integration\Xero\Domain\XeroConnection;
use App\Modules\Sale\Domain\Sale;

class XeroInvoiceSaleService
{
    public function __construct(private readonly XeroApiService $apiService) {}

    public function createInvoice(XeroConnection $connection, Sale $sale): array
    {
        $payload = $sale->getPayload();
        $today = now()->format('Y-m-d');

        $xeroPayload = [
            'Type'    => 'ACCREC',
            'Status'  => 'AUTHORISED',
            'Date'    => $today,
            'DueDate' => $payload['dueDate'] ?? $today,
            'Contact' => [
                'Name' => $sale->buyer->name,
            ],
            'LineItems' => $sale->lineItems->map(
                fn($item) => [
                    'ItemCode' => $item->code,
                    'Description' => $item->name,
                    'Quantity' => $item->quantity,
                    'UnitAmount' => $item->unit_price,
                    'AccountCode' => $item->account_code,
                ]
            )->toArray(),
        ];

        $response = $this->apiService->post($connection, 'Invoices', $xeroPayload);

        return $response->json();
    }
}
