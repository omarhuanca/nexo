<?php

namespace App\Modules\Integration\Xero\Service;

use App\Modules\Integration\Xero\Domain\XeroConnection;

class XeroItemService
{
    public function __construct(private readonly XeroApiService $apiService) {}

    public function syncItems(XeroConnection $connection, array $items): array
    {
        $payload = [
            'Items' => array_map(fn(array $item) => $this->mapItem($item), $items),
        ];

        $response = $this->apiService->post($connection, 'Items', $payload);

        return $response->json();
    }

    private function mapItem(array $item): array
    {
        $mapped = [
            'Code' => $item['code'],
            'Name' => $item['name'],
            'IsSold' => true,
            'IsPurchased' => true,
            'IsTrackedAsInventory' => false,
            'SalesDetails' => [
                'UnitPrice' => $item['salePrice'],
                'AccountCode' => $item['salesAccountCode'],
            ],
            'PurchaseDetails' => [
                'UnitPrice' => $item['costPrice'],
                'AccountCode' => $item['purchaseAccountCode'],
            ],
        ];

        if (!empty($item['description'])) {
            $mapped['Description'] = $item['description'];
        }

        return $mapped;
    }
}
