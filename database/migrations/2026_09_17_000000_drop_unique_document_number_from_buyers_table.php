<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyers', function (Blueprint $table) {
            $table->dropUnique('uniq_buyers_org_document_number');
            $table->index(['organization_id', 'document_number'], 'idx_buyers_org_document_number');
        });
    }

    public function down(): void
    {
        Schema::table('buyers', function (Blueprint $table) {
            $table->dropIndex('idx_buyers_org_document_number');
            $table->unique(['organization_id', 'document_number'], 'uniq_buyers_org_document_number');
        });
    }
};
