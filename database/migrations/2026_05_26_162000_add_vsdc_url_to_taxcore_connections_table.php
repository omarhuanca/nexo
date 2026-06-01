<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxcore_connections', function (Blueprint $table) {
            $table->string('vsdc_url')->nullable()->after('environment');
        });
    }

    public function down(): void
    {
        Schema::table('taxcore_connections', function (Blueprint $table) {
            $table->dropColumn('vsdc_url');
        });
    }
};
