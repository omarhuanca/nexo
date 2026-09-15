<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_items', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->after('sale_id')
                ->constrained('products')
                ->onDelete('restrict');
            $table->index('product_id', 'idx_line_items_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('line_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex('idx_line_items_product_id');
            $table->dropColumn('product_id');
        });
    }
};
