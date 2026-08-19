<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('code', 30);
            $table->string('name', 2048);
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('total_amount', 14, 4);
            $table->jsonb('labels');
            $table->string('account_code', 10);
            $table->string('gtin', 14)->nullable();
            $table->timestamps();

            $table->index('sale_id', 'idx_line_items_sale_id');
            $table->index('code', 'idx_line_items_code');
            $table->index(['sale_id', 'code'], 'idx_line_items_sale_code');
        });

        // CHECK constraints via raw SQL (Laravel doesn't support CHECK in schema builder portably)
        // NOTE: gtin format is validated in LineItem::at() with regex, not in DB,
        // because regex operators are not portable to SQLite (which is used in tests).
        \DB::statement('ALTER TABLE line_items ADD CONSTRAINT chk_line_items_quantity CHECK (quantity > 0 AND quantity <= 999999)');
        \DB::statement('ALTER TABLE line_items ADD CONSTRAINT chk_line_items_unit_price CHECK (unit_price >= 0 AND unit_price <= 999999999.99)');
        \DB::statement('ALTER TABLE line_items ADD CONSTRAINT chk_line_items_total_amount CHECK (total_amount >= 0 AND total_amount <= 999999999.99)');
    }

    public function down(): void
    {
        Schema::dropIfExists('line_items');
    }
};
