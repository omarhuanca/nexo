<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyers', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('id')->constrained('sales')->onDelete('cascade');
        });

        // Buyers shared by more than one sale are leftovers from the old
        // dedup-by-document-number era and cannot map 1:1 to a single sale.
        $sharedBuyerIds = DB::table('sales')
            ->select('buyer_id')
            ->whereNotNull('buyer_id')
            ->groupBy('buyer_id')
            ->havingRaw('count(*) > 1')
            ->pluck('buyer_id');

        if ($sharedBuyerIds->isNotEmpty()) {
            DB::table('buyers')->whereIn('id', $sharedBuyerIds)->delete();
        }

        DB::table('sales')
            ->whereNotNull('buyer_id')
            ->whereNotIn('buyer_id', $sharedBuyerIds)
            ->select('id', 'buyer_id')
            ->orderBy('id')
            ->get()
            ->each(function ($sale) {
                DB::table('buyers')->where('id', $sale->buyer_id)->update(['sale_id' => $sale->id]);
            });

        // Any buyer left without a sale_id has no sale to belong to anymore.
        DB::table('buyers')->whereNull('sale_id')->delete();

        Schema::table('buyers', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_id')->nullable(false)->change();
            $table->unique('sale_id', 'uniq_buyers_sale_id');
        });

        Schema::table('buyers', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex('idx_buyers_organization');
            $table->dropIndex('idx_buyers_org_name');
            $table->dropIndex('idx_buyers_org_document_number');
            $table->dropColumn(['organization_id', 'active']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['buyer_id']);
            $table->dropIndex('idx_sales_buyer_id');
            $table->dropColumn('buyer_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('buyer_id')->nullable()->after('connector_id')->constrained()->onDelete('set null');
            $table->index('buyer_id', 'idx_sales_buyer_id');
        });

        Schema::table('buyers', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained()->onDelete('restrict');
            $table->boolean('active')->default(true);
            $table->index('organization_id', 'idx_buyers_organization');
            $table->index(['organization_id', 'name'], 'idx_buyers_org_name');
        });

        Schema::table('buyers', function (Blueprint $table) {
            $table->dropUnique('uniq_buyers_sale_id');
            $table->dropConstrainedForeignId('sale_id');
        });
    }
};
