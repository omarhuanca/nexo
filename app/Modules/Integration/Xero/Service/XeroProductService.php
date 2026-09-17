<?php

namespace App\Modules\Integration\Xero\Service;

use App\Modules\Integration\Xero\Domain\XeroProduct;
use App\Modules\Product\Domain\Product;
use App\Shared\Exceptions\BusinessConflictException;

class XeroProductService
{
    public function configure(
        Product $product,
        string $salesAccountCode,
        string $purchaseAccountCode,
    ): XeroProduct {
        if ($product->xeroProduct()->exists()) {
            throw new BusinessConflictException(
                'This product already has Xero configuration.'
            );
        }

        return XeroProduct::create([
            'product_id' => $product->id,
            'sales_account_code' => trim($salesAccountCode),
            'purchase_account_code' => trim($purchaseAccountCode),
        ]);
    }
}
