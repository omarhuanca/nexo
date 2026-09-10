<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('code', 30);
            $table->string('name', 255);
            $table->string('description', 2048)->default('');
            $table->decimal('sale_price', 14, 4);
            $table->decimal('cost_price', 14, 4);
            $table->timestamps();

            $table->unique(
                ['organization_id', 'code'],
                'products_organization_code_unique'
            );
            $table->index('organization_id', 'idx_products_organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
