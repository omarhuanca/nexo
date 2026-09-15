<?php

namespace App\Modules\Integration\Xero\Domain;

use App\Modules\Product\Domain\Product;
use App\Shared\Domain\BaseEntity;

class XeroProduct extends BaseEntity
{
    protected $table = 'xero_products';

    protected $fillable = [
        'product_id',
        'sales_account_code',
        'purchase_account_code',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
