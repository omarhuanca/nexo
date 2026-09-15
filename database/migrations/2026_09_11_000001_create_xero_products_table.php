<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xero_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('sales_account_code', 10);
            $table->string('purchase_account_code', 10);
            $table->timestamps();

            $table->unique('product_id', 'xero_products_product_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xero_products');
    }
};
