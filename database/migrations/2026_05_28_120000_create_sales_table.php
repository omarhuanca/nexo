<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('connector_id')->constrained()->onDelete('cascade');

            $table->string('status')->default('pending');
            $table->jsonb('payload');

            // Xero result
            $table->string('xero_invoice_id')->nullable();
            $table->jsonb('xero_result')->nullable();

            // TaxCore result
            $table->string('fiscal_number')->nullable();
            $table->jsonb('fiscal_result')->nullable();

            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['connector_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
