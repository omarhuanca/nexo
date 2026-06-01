<?php

namespace App\Modules\Integration\Xero\Service;

use App\Modules\Integration\Xero\Domain\XeroConnection;

class XeroInvoiceSaleService
{
    public function __construct(private readonly XeroApiService $apiService) {}

    public function createInvoice(XeroConnection $connection, array $payload): array
    {
        $today = now()->format('Y-m-d');

        $xeroPayload = [
            'Type'    => 'ACCREC',
            'Status'  => 'AUTHORISED',
            'Date'    => $today,
            'DueDate' => $payload['dueDate'] ?? $today,
            'Contact' => [
                'Name' => $payload['buyer']['name'],
            ],
            'LineItems' => array_map(
                fn(array $item) => [
                    'ItemCode' => $item['code'],
                    'Description' => $item['name'],
                    'Quantity' => $item['quantity'],
                    'UnitAmount' => $item['unitPrice'],
                    'AccountCode' => $item['accountCode'],
                ],
                $payload['items']
            ),
        ];

        $response = $this->apiService->post($connection, 'Invoices', $xeroPayload);

        return $response->json();
    }
}
