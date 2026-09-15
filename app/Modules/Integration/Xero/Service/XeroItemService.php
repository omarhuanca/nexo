<?php

namespace App\Modules\Integration\Xero\Service;

use App\Modules\Integration\Xero\Domain\XeroConnection;
use App\Modules\Product\Repository\ProductRepository;

class XeroItemService
{
    public function __construct(
        private readonly XeroApiService $apiService,
        private readonly ProductRepository $productRepository,
    ) {}

    public function syncItems(XeroConnection $connection, array $productIds): array
    {
        $items = [];

        foreach ($productIds as $productId) {
            $product = $this->productRepository->findById($productId);

            if (!$product || !$product->xeroProduct) {
                continue;
            }

            $items[] = $this->mapItem($product);
        }

        if (empty($items)) {
            return ['Items' => []];
        }

        $payload = ['Items' => $items];

        $response = $this->apiService->post($connection, 'Items', $payload);

        return $response->json();
    }

    private function mapItem($product): array
    {
        $xeroProduct = $product->xeroProduct;

        $mapped = [
            'Code' => $product->code,
            'Name' => $product->name,
            'IsSold' => true,
            'IsPurchased' => true,
            'IsTrackedAsInventory' => false,
            'SalesDetails' => [
                'UnitPrice' => $product->sale_price,
                'AccountCode' => $xeroProduct->sales_account_code,
            ],
            'PurchaseDetails' => [
                'UnitPrice' => $product->cost_price,
                'AccountCode' => $xeroProduct->purchase_account_code,
            ],
        ];

        if (!empty($product->description)) {
            $mapped['Description'] = $product->description;
        }

        return $mapped;
    }
}
