<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('ALTER TABLE line_items DROP CONSTRAINT IF EXISTS chk_line_items_quantity');
        }

        Schema::table('line_items', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE line_items ADD CONSTRAINT chk_line_items_quantity CHECK (quantity > 0 AND quantity <= 999999)');
        }
    }

    public function down(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('ALTER TABLE line_items DROP CONSTRAINT IF EXISTS chk_line_items_quantity');
        }

        Schema::table('line_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->change();
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE line_items ADD CONSTRAINT chk_line_items_quantity CHECK (quantity > 0 AND quantity <= 999999)');
        }
    }
};
