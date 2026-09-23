<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connectors', function (Blueprint $table) {
            $table->string('callback_url', 2048)->nullable()->after('allowed_events');
            $table->text('callback_secret')->nullable()->after('callback_url');
        });
    }

    public function down(): void
    {
        Schema::table('connectors', function (Blueprint $table) {
            $table->dropColumn(['callback_url', 'callback_secret']);
        });
    }
};
