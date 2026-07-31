<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained()
                ->onDelete('restrict');
            $table->string('name', 255);
            $table->string('tax_id', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'tax_id'], 'uniq_buyers_org_tax');
            $table->index('organization_id', 'idx_buyers_organization');
            $table->index(['organization_id', 'name'], 'idx_buyers_org_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyers');
    }
};
