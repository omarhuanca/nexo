<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')
                ->constrained()
                ->onDelete('cascade');
            $table->decimal('amount', 14, 4);
            $table->smallInteger('payment_type');
            $table->smallInteger('sequence')->default(0);
            $table->timestamps();

            $table->index('sale_id', 'idx_payments_sale_id');
            $table->index(['sale_id', 'sequence'], 'idx_payments_sale_sequence');

            $table->index('payment_type', 'idx_payments_type');
        });

        // CHECK constraints via raw SQL (Laravel doesn't support CHECK in schema builder portably)
        \DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_amount CHECK (amount > 0)');
        \DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_type CHECK (payment_type >= 0 AND payment_type <= 6)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
