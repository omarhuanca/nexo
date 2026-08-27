<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('buyer_id')
                ->nullable()
                ->after('connector_id')
                ->constrained()
                ->onDelete('set null');

            $table->index('buyer_id', 'idx_sales_buyer_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['buyer_id']);
            $table->dropIndex('idx_sales_buyer_id');
            $table->dropColumn('buyer_id');
        });
    }
};
